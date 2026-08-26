<?php

namespace Tests\Feature\Exams;

use App\Enums\ExamQuestionSelectionMode;
use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\QuestionTranslation;
use App\Models\StudentSurvey;
use App\Models\TranslationLanguage;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExamTranslationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Course $subject;

    private Group $group;

    /**
     * Overrides the default forward "[target] text" fake for any /translate
     * call whose target is the source language (i.e. a back-translation
     * call) - set by a test right before triggering one. Kept as mutable
     * state read by the single Http::fake() closure registered in setUp(),
     * rather than calling Http::fake() again mid-test: Laravel appends
     * fake callbacks instead of replacing them, so a later Http::fake()
     * call never actually overrides an earlier catch-all one.
     */
    private ?\Closure $backTranslationBehavior = null;

    /**
     * Same mutable-closure pattern as $backTranslationBehavior, but for the
     * forward (source -> target) Question/Option translation call.
     */
    private ?\Closure $forwardTranslationBehavior = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->create();
        $this->subject = Course::factory()->create();
        $this->group = Group::create(['name' => 'Translation Group', 'status' => 'active']);
        $this->addToGroup($this->student);

        TranslationLanguage::factory()->arabic()->enabled()->create();
        $this->fakeTranslationProvider();
    }

    // --- Question/Option cache reuse ---------------------------------------

    public function test_question_and_option_translations_are_cached_and_reused_across_exams(): void
    {
        $question = $this->makeQuestionWithOptions('What is formation pressure?');
        $scheduleA = $this->makeSchedule($this->makeExamWithQuestion($question));

        $this->startAs($this->student, $scheduleA, 'ar');

        // One combined provider call: the Question text plus its 3 Option texts.
        Http::assertSentCount(1);
        $this->assertDatabaseCount('question_translations', 1);
        $this->assertDatabaseCount('question_option_translations', 3);

        $attempt = $this->latestAttempt($this->student);
        $this->assertSame('ar', $attempt->language_code);
        $this->assertSame('[ar] What is formation pressure?', $attempt->attemptQuestions()->firstOrFail()->translated_question_text);

        $studentTwo = $this->newGroupedStudent();
        $scheduleB = $this->makeSchedule($this->makeExamWithQuestion($question));
        $this->startAs($studentTwo, $scheduleB, 'ar');

        // Same Question, same language, different Exam - reused from cache, no new provider call.
        Http::assertSentCount(1);
        $this->assertDatabaseCount('question_translations', 1);
    }

    public function test_stale_translation_is_not_reused_after_question_text_changes(): void
    {
        $question = $this->makeQuestionWithOptions('Original text');
        $exam = $this->makeExamWithQuestion($question);
        $this->startAs($this->student, $this->makeSchedule($exam), 'ar');
        Http::assertSentCount(1);
        $originalHash = QuestionTranslation::query()->firstOrFail()->source_hash;

        $question->update(['question_text' => 'Updated text']);
        $this->assertNotSame($originalHash, $question->fresh()->question_text_hash);

        $studentTwo = $this->newGroupedStudent();
        $this->startAs($studentTwo, $this->makeSchedule($exam), 'ar');

        Http::assertSentCount(2);
        $this->assertDatabaseCount('question_translations', 1);
        $this->assertSame('[ar] Updated text', QuestionTranslation::query()->firstOrFail()->translated_text);
    }

    public function test_only_the_randomly_selected_questions_are_translated_not_the_whole_pool(): void
    {
        $questions = collect(range(1, 5))->map(fn (int $i): Question => $this->makeQuestionWithOptions("Random question {$i}"));
        $exam = Exam::create([
            'course_id' => $this->subject->id,
            'name' => 'Random Translated Exam',
            'question_order_mode' => 'static',
            'question_selection_mode' => ExamQuestionSelectionMode::Random,
            'question_count' => 2,
            'status' => ExamStatus::Published,
        ]);

        $this->startAs($this->student, $this->makeSchedule($exam), 'ar');

        $this->assertDatabaseCount('question_translations', 2);
        $translatedQuestionIds = QuestionTranslation::query()->pluck('question_id');
        $this->assertTrue($translatedQuestionIds->every(fn (int $id): bool => $questions->pluck('id')->contains($id)));
    }

    public function test_attempt_translation_snapshot_is_immutable_after_question_text_changes(): void
    {
        $question = $this->makeQuestionWithOptions('Snapshot text');
        $this->startAs($this->student, $this->makeSchedule($this->makeExamWithQuestion($question)), 'ar');

        $attemptQuestion = $this->latestAttempt($this->student)->attemptQuestions()->firstOrFail();
        $this->assertSame('[ar] Snapshot text', $attemptQuestion->translated_question_text);

        $question->update(['question_text' => 'Changed after attempt started']);

        $this->assertSame('[ar] Snapshot text', $attemptQuestion->fresh()->translated_question_text);
    }

    public function test_question_translation_failure_falls_back_to_source_text_and_never_renders_it_under_the_target_direction(): void
    {
        // Reproduces LibreTranslate's real hosted-instance behavior when no
        // API key is configured: HTTP 400 with an error body, for every item
        // in the batch - never a thrown exception.
        $this->forwardTranslationBehavior = fn () => Http::response(['error' => 'Visit https://portal.libretranslate.com to get an API key'], 400);

        $question = $this->makeQuestionWithOptions('What is formation pressure?');
        $this->startAs($this->student, $this->makeSchedule($this->makeExamWithQuestion($question)), 'ar')->assertRedirect();

        $attempt = $this->latestAttempt($this->student);
        $this->assertSame('ar', $attempt->language_code);

        $attemptQuestion = $attempt->attemptQuestions()->firstOrFail();
        $this->assertSame('What is formation pressure?', $attemptQuestion->translated_question_text);
        $this->assertSame('failed', $attemptQuestion->question_translation_status->value);
        $this->assertSame('ltr', $attemptQuestion->displayDirection(\App\Enums\LanguageDirection::Rtl)->value);

        // No bad/partial data persisted to the reusable cache - safely retried later.
        $this->assertDatabaseCount('question_translations', 0);
        $this->assertDatabaseCount('question_option_translations', 0);
    }

    // --- Original language / provider independence --------------------------

    public function test_original_language_start_never_calls_the_provider(): void
    {
        $question = $this->makeQuestionWithOptions('No translation needed');
        $this->startAs($this->student, $this->makeSchedule($this->makeExamWithQuestion($question)));

        Http::assertNothingSent();
        $attempt = $this->latestAttempt($this->student);
        $this->assertNull($attempt->language_code);
        $this->assertNull($attempt->attemptQuestions()->firstOrFail()->translated_question_text);
    }

    public function test_student_cannot_start_with_a_disabled_or_unknown_language(): void
    {
        $question = $this->makeQuestionWithOptions('Guarded question');
        $schedule = $this->makeSchedule($this->makeExamWithQuestion($question));
        TranslationLanguage::factory()->create(['code' => 'fr', 'is_enabled' => false]);

        $this->startAs($this->student, $schedule, 'fr')->assertSessionHasErrors('language_code');
        $this->assertDatabaseCount('exam_attempts', 0);

        $this->startAs($this->student, $schedule, 'zz')->assertSessionHasErrors('language_code');
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    // --- MCQ scoring identity -------------------------------------------------

    public function test_mcq_scoring_uses_option_public_id_not_translated_text(): void
    {
        $question = $this->makeQuestionWithOptions('Pressure question');
        $correctOption = $question->options->firstWhere('is_correct', true);
        $exam = $this->makeExamWithQuestion($question, passingScore: 50);

        $this->startAs($this->student, $this->makeSchedule($exam), 'ar');
        $attempt = $this->latestAttempt($this->student);
        $attemptQuestion = $attempt->attemptQuestions()->firstOrFail();

        $this->assertStringStartsWith('[ar]', $attemptQuestion->translated_options[$correctOption->public_id]);

        $this->answerAs($this->student, $attempt, $attemptQuestion, $correctOption->public_id)->assertOk();
        $this->submitAs($this->student, $attempt)->assertRedirect();

        $this->assertSame(100.0, (float) $attempt->fresh()->score);
        $this->assertTrue($attempt->fresh()->passed);
    }

    // --- Text Input back-translation ------------------------------------------

    public function test_text_input_back_translation_scores_correctly_against_the_canonical_answer(): void
    {
        [$attempt, $attemptQuestion] = $this->startInputAttempt('What is the capital of France?', 'Paris', passingScore: 50);
        $this->answerAs($this->student, $attempt, $attemptQuestion, 'باريس')->assertOk();

        $this->backTranslationBehavior = fn (array $texts) => Http::response(['translatedText' => array_fill(0, count($texts), 'Paris')]);
        $this->submitAs($this->student, $attempt)->assertRedirect();

        $attemptQuestion->refresh();
        $this->assertSame('translated', $attemptQuestion->answer_translation_status->value);
        $this->assertSame('Paris', $attemptQuestion->back_translated_answer);
        $this->assertSame(100.0, (float) $attempt->fresh()->score);
        $this->assertTrue($attempt->fresh()->passed);
    }

    public function test_text_input_translation_failure_never_marks_the_answer_incorrect_and_is_retried_at_certificate_issuance(): void
    {
        [$attempt, $attemptQuestion] = $this->startInputAttempt('What is the capital of France?', 'Paris', passingScore: 50);
        $this->answerAs($this->student, $attempt, $attemptQuestion, 'باريس')->assertOk();

        // Submit-time back-translation call fails; the retry inside
        // IssueCertificateAction (same request, via SubmitExamAttemptAction's
        // controller-level follow-up call) succeeds.
        $calls = 0;
        $this->backTranslationBehavior = function (array $texts) use (&$calls) {
            $calls++;

            return $calls === 1
                ? Http::response(['error' => 'server error'], 500)
                : Http::response(['translatedText' => array_fill(0, count($texts), 'Paris')]);
        };

        $this->submitAs($this->student, $attempt)->assertRedirect();

        $attemptQuestion->refresh();
        $this->assertSame('translated', $attemptQuestion->answer_translation_status->value);
        $this->assertSame('Paris', $attemptQuestion->back_translated_answer);
        $this->assertSame(100.0, (float) $attempt->fresh()->score);
        $this->assertTrue($attempt->fresh()->passed);
    }

    public function test_persistent_text_input_translation_failure_excludes_the_question_from_scoring_instead_of_marking_it_wrong(): void
    {
        [$attempt, $attemptQuestion] = $this->startInputAttempt('What is the capital of France?', 'Paris', passingScore: 50);
        $this->answerAs($this->student, $attempt, $attemptQuestion, 'باريس')->assertOk();

        $this->backTranslationBehavior = fn () => Http::response(['error' => 'down'], 500);

        $this->submitAs($this->student, $attempt)->assertRedirect();

        $attemptQuestion->refresh();
        $this->assertSame('failed', $attemptQuestion->answer_translation_status->value);
        $this->assertNull($attemptQuestion->back_translated_answer);
        // Excluded from scoring (never counted incorrect): with this the only
        // question and it still ungraded, there is no gradable content yet.
        $this->assertSame(0.0, (float) $attempt->fresh()->score);
        $this->assertFalse((bool) $attempt->fresh()->passed);
    }

    // --- Admin language management ---------------------------------------------

    public function test_admin_can_sync_language_catalog_and_enable_languages(): void
    {
        Http::fake(fn () => Http::response([
            ['code' => 'ar', 'name' => 'Arabic', 'targets' => ['en']],
            ['code' => 'fr', 'name' => 'French', 'targets' => ['en']],
        ]));

        $this->asAdmin()->postJson(route('admin.settings.exam-languages.sync'))->assertOk();

        $this->assertDatabaseHas('translation_languages', ['code' => 'fr', 'is_enabled' => false]);
        $frenchId = TranslationLanguage::query()->where('code', 'fr')->value('id');

        $this->asAdmin()->patchJson(route('admin.settings.exam-languages.update'), ['enabled_ids' => [$frenchId]])->assertOk();

        $this->assertDatabaseHas('translation_languages', ['id' => $frenchId, 'is_enabled' => true]);
    }

    public function test_non_admin_roles_cannot_access_exam_language_settings(): void
    {
        $this->actingAs($this->student)->withSession(['auth.session_version' => $this->student->session_version])
            ->get(route('admin.settings.exam-languages.index'))
            ->assertForbidden();
    }

    // --- Helpers ---------------------------------------------------------------

    /** @return array{0: ExamAttempt, 1: \App\Models\ExamAttemptQuestion} */
    private function startInputAttempt(string $questionText, string $correctAnswer, int $passingScore): array
    {
        $question = Question::create([
            'course_id' => $this->subject->id,
            'question_text' => $questionText,
            'type' => 'input',
            'difficulty' => 'easy',
            'default_marks' => 1,
            'correct_answer_text' => $correctAnswer,
            'is_active' => true,
        ]);
        $exam = $this->makeExamWithQuestion($question, passingScore: $passingScore);
        $this->startAs($this->student, $this->makeSchedule($exam), 'ar');

        $attempt = $this->latestAttempt($this->student);

        return [$attempt, $attempt->attemptQuestions()->firstOrFail()];
    }

    private function makeQuestionWithOptions(string $text): Question
    {
        $question = Question::create([
            'course_id' => $this->subject->id,
            'question_text' => $text,
            'type' => 'mcq',
            'difficulty' => 'easy',
            'default_marks' => 2,
            'is_active' => true,
        ]);

        foreach (['Alpha', 'Beta', 'Gamma'] as $index => $optionText) {
            $question->options()->create(['option_text' => $optionText, 'is_correct' => $index === 0, 'display_order' => $index]);
        }

        return $question->fresh('options');
    }

    private function makeExamWithQuestion(Question $question, int $passingScore = 75): Exam
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id, 'passing_score' => $passingScore]);
        $exam->examQuestions()->create(['question_id' => $question->id, 'display_order' => 1, 'points' => $question->default_marks]);

        return $exam;
    }

    private function makeSchedule(Exam $exam): \App\Models\ExamSchedule
    {
        return \App\Models\ExamSchedule::create([
            'exam_id' => $exam->id,
            'group_id' => $this->group->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);
    }

    private function addToGroup(User $student): void
    {
        GroupMembership::create([
            'group_id' => $this->group->id,
            'student_user_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    private function newGroupedStudent(): User
    {
        $student = User::factory()->student()->create();
        $this->addToGroup($student);

        return $student;
    }

    private function latestAttempt(User $student): ExamAttempt
    {
        return ExamAttempt::query()->where('student_user_id', $student->id)->latest('id')->firstOrFail();
    }

    private function startAs(User $student, \App\Models\ExamSchedule $schedule, ?string $languageCode = null): TestResponse
    {
        StudentSurvey::updateOrCreate(
            ['student_user_id' => $student->id, 'exam_schedule_id' => $schedule->id],
            ['status' => 'completed', 'contact_confirmed_at' => now(), 'completed_at' => now()],
        );

        return $this->actingAs($student)
            ->withSession(['auth.session_version' => $student->session_version])
            ->post(route('student.exams.start', $schedule), ['language_code' => $languageCode]);
    }

    private function answerAs(User $student, ExamAttempt $attempt, \App\Models\ExamAttemptQuestion $attemptQuestion, string $answer): TestResponse
    {
        return $this->actingAs($student)
            ->withSession(['auth.session_version' => $student->session_version])
            ->patch(route('student.attempts.answers.update', [$attempt, $attemptQuestion]), ['answer' => $answer]);
    }

    private function submitAs(User $student, ExamAttempt $attempt): TestResponse
    {
        return $this->actingAs($student)
            ->withSession(['auth.session_version' => $student->session_version])
            ->post(route('student.attempts.submit', $attempt));
    }

    private function asAdmin(): TestResponse|\Illuminate\Foundation\Testing\TestCase
    {
        return $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
    }

    /**
     * The single Http::fake() for the whole test: forward Question/Option
     * translation always echoes "[target] text"; a /translate call whose
     * target is the source language (a back-translation call) defers to
     * $this->backTranslationBehavior when a test has set one, so tests never
     * need to call Http::fake() again mid-test (see backTranslationBehavior's
     * docblock for why that wouldn't work).
     */
    private function fakeTranslationProvider(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/languages')) {
                return Http::response([
                    ['code' => 'ar', 'name' => 'Arabic', 'targets' => ['en']],
                    ['code' => 'fr', 'name' => 'French', 'targets' => ['en']],
                ]);
            }

            if (str_ends_with($request->url(), '/translate')) {
                $data = $request->data();
                $target = $data['target'];

                if ($target === config('translation.source_language') && $this->backTranslationBehavior) {
                    return ($this->backTranslationBehavior)($data['q']);
                }

                if ($target !== config('translation.source_language') && $this->forwardTranslationBehavior) {
                    return ($this->forwardTranslationBehavior)($data['q']);
                }

                return Http::response([
                    'translatedText' => collect($data['q'])->map(fn (string $text): string => "[{$target}] {$text}")->all(),
                ]);
            }

            return Http::response([], 404);
        });
    }
}
