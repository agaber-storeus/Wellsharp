<?php

namespace Tests\Feature\Exams;

use App\Enums\ExamQuestionSelectionMode;
use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\StudentSurvey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RandomQuestionSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $studentOne;

    private User $studentTwo;

    private Course $subject;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create();
        $this->studentOne = User::factory()->student()->create();
        $this->studentTwo = User::factory()->student()->create();
        $this->subject = Course::factory()->create();
        $this->group = Group::create(['name' => 'Random Exam Group', 'status' => 'active']);

        foreach ([$this->studentOne, $this->studentTwo] as $student) {
            GroupMembership::create([
                'group_id' => $this->group->id,
                'student_user_id' => $student->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        $this->asAdmin();
    }

    public function test_existing_manual_exam_defaults_to_manual_selection_and_keeps_questions(): void
    {
        $question = $this->makeQuestion('Manual question');

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Manual Exam',
            'question_order_mode' => 'static',
            'status' => 'draft',
            'question_ids' => [$question->id],
            'display_orders' => [$question->id => 1],
        ])->assertRedirect();

        $exam = Exam::query()->where('name', 'Manual Exam')->firstOrFail();
        $this->assertSame(ExamQuestionSelectionMode::Manual, $exam->question_selection_mode);
        $this->assertNull($exam->question_count);
        $this->assertSame([$question->id], $exam->examQuestions()->pluck('question_id')->all());
    }

    public function test_manual_selection_still_supports_shuffle_order(): void
    {
        $question = $this->makeQuestion('Manual shuffle question');

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Manual Shuffle Exam',
            'question_order_mode' => 'shuffle',
            'status' => 'draft',
            'question_ids' => [$question->id],
            'display_orders' => [$question->id => 1],
        ])->assertRedirect();

        $exam = Exam::query()->where('name', 'Manual Shuffle Exam')->firstOrFail();
        $this->assertSame(ExamQuestionSelectionMode::Manual, $exam->question_selection_mode);
        $this->assertSame('shuffle', $exam->question_order_mode->value);
    }

    public function test_admin_can_create_and_update_a_random_exam_without_exam_question_assignments(): void
    {
        $this->makeQuestions(5);

        $this->post(route('admin.exams.store'), [
            'course_id' => $this->subject->id,
            'name' => 'Random Exam',
            'question_order_mode' => 'static',
            'question_selection_mode' => 'random',
            'question_count' => 3,
            'status' => 'published',
        ])->assertRedirect();

        $exam = Exam::query()->where('name', 'Random Exam')->firstOrFail();
        $this->assertSame(ExamQuestionSelectionMode::Random, $exam->question_selection_mode);
        $this->assertSame(3, $exam->question_count);
        $this->assertSame('static', $exam->question_order_mode->value);
        $this->assertDatabaseCount('exam_questions', 0);

        $manualQuestion = $this->makeQuestion('Question removed when switching to random');
        $manualExam = Exam::create([
            'course_id' => $this->subject->id,
            'name' => 'Manual To Random',
            'question_order_mode' => 'static',
            'status' => ExamStatus::Draft,
        ]);
        ExamQuestion::create(['exam_id' => $manualExam->id, 'question_id' => $manualQuestion->id, 'display_order' => 1]);

        $this->put(route('admin.exams.update', $manualExam), [
            'course_id' => $this->subject->id,
            'name' => 'Manual To Random',
            'question_order_mode' => 'shuffle',
            'question_selection_mode' => 'random',
            'question_count' => 2,
            'status' => 'draft',
        ])->assertRedirect();

        $updated = $manualExam->fresh();
        $this->assertSame(ExamQuestionSelectionMode::Random, $updated->question_selection_mode);
        $this->assertSame(2, $updated->question_count);
        $this->assertSame('static', $updated->question_order_mode->value);
        $this->assertDatabaseMissing('exam_questions', ['exam_id' => $manualExam->id]);
    }

    public function test_random_mode_normalizes_a_tampered_shuffle_order_to_static(): void
    {
        $this->makeQuestions(3);

        $this->post(route('admin.exams.store'), [
            'course_id' => $this->subject->id,
            'name' => 'Tampered Random Exam',
            'question_order_mode' => 'shuffle',
            'question_selection_mode' => 'random',
            'question_count' => 2,
            'status' => 'draft',
        ])->assertRedirect();

        $exam = Exam::query()->where('name', 'Tampered Random Exam')->firstOrFail();
        $this->assertSame('static', $exam->question_order_mode->value);
    }

    public function test_random_exam_details_do_not_show_the_questions_section(): void
    {
        [$exam] = $this->makeRandomExam();

        $this->get(route('admin.exams.show', $exam))
            ->assertOk()
            ->assertDontSee('<h3>Questions</h3>', false)
            ->assertSee('<h3>Schedules</h3>', false);
    }

    public function test_manual_exam_details_continue_to_show_the_questions_section(): void
    {
        $question = $this->makeQuestion('Manual details question');
        $exam = Exam::create([
            'course_id' => $this->subject->id,
            'name' => 'Manual Details Exam',
            'question_order_mode' => 'static',
            'status' => ExamStatus::Draft,
        ]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'display_order' => 1]);

        $this->get(route('admin.exams.show', $exam))
            ->assertOk()
            ->assertSee('<h3>Questions</h3>', false)
            ->assertSee('Manual details question');
    }

    public function test_random_question_count_must_be_positive_and_within_the_active_subject_pool(): void
    {
        $this->makeQuestions(5);
        $payload = [
            'course_id' => $this->subject->id,
            'name' => 'Invalid Random Exam',
            'question_order_mode' => 'static',
            'question_selection_mode' => 'random',
            'status' => 'draft',
        ];

        $this->post(route('admin.exams.store'), $payload + ['question_count' => 0])
            ->assertSessionHasErrors('question_count');
        $this->post(route('admin.exams.store'), $payload + ['question_count' => 6])
            ->assertSessionHasErrors('question_count');
        $this->assertDatabaseMissing('exams', ['name' => 'Invalid Random Exam']);
    }

    public function test_random_questions_are_not_assigned_before_start_and_are_fixed_per_attempt(): void
    {
        [$exam, $questions, $schedule] = $this->makeRandomExam();
        $this->assertSame(0, $exam->fresh()->examQuestions()->count());
        $this->assertDatabaseCount('exam_attempt_questions', 0);

        $this->startAs($this->studentOne, $schedule);
        $firstAttempt = ExamAttempt::query()->where('student_user_id', $this->studentOne->id)->firstOrFail();
        $firstQuestionIds = $firstAttempt->attemptQuestions()->pluck('question_id')->all();

        $this->assertCount(3, $firstQuestionIds);
        $this->assertSame($firstQuestionIds, $firstAttempt->fresh()->attemptQuestions()->pluck('question_id')->all());
        $this->assertTrue(collect($firstQuestionIds)->every(fn (int $id): bool => $questions->pluck('id')->contains($id)));
    }

    public function test_repeated_start_is_idempotent_and_two_students_have_separate_assignment_lifecycles(): void
    {
        [, , $schedule] = $this->makeRandomExam();

        $this->startAs($this->studentOne, $schedule);
        $firstAttempt = ExamAttempt::query()->where('student_user_id', $this->studentOne->id)->firstOrFail();
        $firstQuestionIds = $firstAttempt->attemptQuestions()->pluck('question_id')->all();

        $this->startAs($this->studentOne, $schedule);
        $this->assertDatabaseCount('exam_attempts', 1);
        $this->assertSame($firstQuestionIds, $firstAttempt->fresh()->attemptQuestions()->pluck('question_id')->all());

        $this->startAs($this->studentTwo, $schedule);
        $secondAttempt = ExamAttempt::query()->where('student_user_id', $this->studentTwo->id)->firstOrFail();

        $this->assertNotSame($firstAttempt->id, $secondAttempt->id);
        $this->assertCount(3, $firstAttempt->fresh()->attemptQuestions);
        $this->assertCount(3, $secondAttempt->fresh()->attemptQuestions);
        $this->assertSame(6, $secondAttempt->fresh()->attemptQuestions()->count() + $firstAttempt->fresh()->attemptQuestions()->count());
        $this->assertSame(2, ExamAttempt::query()->count());
    }

    public function test_expired_random_attempt_gets_a_new_attempt_and_new_persisted_assignment(): void
    {
        [, , $schedule] = $this->makeRandomExam();
        $this->startAs($this->studentOne, $schedule);
        $firstAttempt = ExamAttempt::query()->where('student_user_id', $this->studentOne->id)->firstOrFail();
        $firstAttempt->update(['expires_at' => now()->subMinute()]);

        $this->startAs($this->studentOne, $schedule);
        $attempts = ExamAttempt::query()->where('student_user_id', $this->studentOne->id)->orderBy('attempt_number')->get();

        $this->assertCount(2, $attempts);
        $this->assertSame('expired', $attempts[0]->status->value);
        $this->assertSame(2, $attempts[1]->attempt_number);
        $this->assertCount(3, $attempts[1]->attemptQuestions);
    }

    public function test_random_start_is_blocked_if_the_active_pool_becomes_too_small(): void
    {
        [$exam, $questions, $schedule] = $this->makeRandomExam();
        $questions->take(3)->each->update(['is_active' => false]);

        $this->startAs($this->studentOne, $schedule)
            ->assertSessionHasErrors('exam');

        $this->assertDatabaseCount('exam_attempts', 0);
        $this->assertSame(ExamQuestionSelectionMode::Random, $exam->fresh()->question_selection_mode);
    }

    private function makeRandomExam(): array
    {
        $questions = $this->makeQuestions(5);
        $exam = Exam::create([
            'course_id' => $this->subject->id,
            'name' => 'Random Start Exam',
            'question_order_mode' => 'static',
            'question_selection_mode' => ExamQuestionSelectionMode::Random,
            'question_count' => 3,
            'status' => ExamStatus::Published,
        ]);
        $schedule = ExamSchedule::create([
            'exam_id' => $exam->id,
            'group_id' => $this->group->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'duration_minutes' => 90,
            'status' => 'scheduled',
        ]);

        return [$exam, $questions, $schedule];
    }

    private function makeQuestions(int $count): Collection
    {
        return collect(range(1, $count))->map(fn (int $number): Question => $this->makeQuestion("Random question {$number}"));
    }

    private function makeQuestion(string $text): Question
    {
        return Question::create([
            'course_id' => $this->subject->id,
            'question_text' => $text,
            'type' => 'input',
            'difficulty' => 'easy',
            'default_marks' => 1,
            'correct_answer_text' => 'answer',
            'is_active' => true,
        ]);
    }

    private function startAs(User $student, ExamSchedule $schedule)
    {
        StudentSurvey::updateOrCreate(
            ['student_user_id' => $student->id, 'exam_schedule_id' => $schedule->id],
            ['status' => 'completed', 'contact_confirmed_at' => now(), 'completed_at' => now()],
        );

        return $this->actingAs($student)
            ->withSession(['auth.session_version' => $student->session_version])
            ->post(route('student.exams.start', $schedule));
    }

    private function asAdmin(): void
    {
        $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
    }
}
