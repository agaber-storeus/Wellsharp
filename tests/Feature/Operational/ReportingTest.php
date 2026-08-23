<?php

namespace Tests\Feature\Operational;

use App\Actions\Certificates\IssueCertificateAction;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_real_assessment_and_retake_metrics(): void
    {
        $fixture = $this->fixture();
        app(IssueCertificateAction::class)->execute($fixture['failedAttempt']);
        app(IssueCertificateAction::class)->execute($fixture['passedAttempt']);

        $this->actingAs($fixture['proctor'])->withSession(['auth.session_version' => $fixture['proctor']->session_version])
            ->get(route('proctor.analytics.results', ['date_range' => 'All Time']))
            ->assertOk()
            ->assertSee('Assessment Comparison')
            ->assertSee('Trainees Retaking Exam')
            ->assertSee('Drilling Assessment')
            ->assertSee('Passed')
            ->assertSee('Failed');

        $this->get(route('proctor.analytics.attempts.show', $fixture['passedAttempt']))
            ->assertOk()
            ->assertSee('Question scoring')
            ->assertSee('Passed')
            ->assertSee('Approved barrier controls');
    }

    public function test_release_finalizes_current_attempt_for_the_assigned_proctor_only(): void
    {
        $fixture = $this->fixture();
        $otherProctor = User::factory()->proctor()->create();

        $this->actingAs($fixture['proctor'])->withSession(['auth.session_version' => $fixture['proctor']->session_version])
            ->post(route('proctor.analytics.attempts.release', $fixture['inProgressAttempt']))
            ->assertRedirect();

        $released = $fixture['inProgressAttempt']->fresh();
        $this->assertSame('submitted', $released->status->value);
        $this->assertSame(0.0, (float) $released->score);
        $this->assertFalse($released->passed);
        $this->assertNotNull($released->released_at);
        $this->assertSame($fixture['proctor']->id, $released->released_by_user_id);
        $this->assertDatabaseHas('audit_events', ['action' => 'exam_attempt.released']);

        // IDOR regression: a Proctor not assigned to this attempt's Class must be
        // rejected, not merely omitted from a list - see BUSINESS_RULES.md ownership rule.
        $this->actingAs($otherProctor)->withSession(['auth.session_version' => $otherProctor->session_version])
            ->get(route('proctor.analytics.attempts.show', $fixture['inProgressAttempt']))
            ->assertForbidden();
    }

    /** @return array{proctor: User, failedAttempt: ExamAttempt, passedAttempt: ExamAttempt, inProgressAttempt: ExamAttempt} */
    private function fixture(): array
    {
        $this->seedRoles();
        $proctor = User::factory()->proctor()->create();
        $student = User::factory()->student()->create();
        $provider = TrainingProvider::factory()->create(['name' => 'Reporting Provider']);
        $course = Course::factory()->create(['name' => 'Reporting Subject']);
        $class = TrainingClass::factory()->create(['course_id' => $course->id, 'training_provider_id' => $provider->id, 'proctor_id' => $proctor->id, 'class_number' => 'REPORT-001']);
        $group = Group::create(['name' => 'Reporting Group', 'status' => 'active']);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Drilling Assessment', 'passing_score' => 50, 'retake_score' => 40, 'question_order_mode' => 'static', 'status' => 'published']);
        $question = Question::create(['course_id' => $course->id, 'question_text' => 'Which barrier is approved?', 'type' => 'mcq', 'difficulty' => 'easy', 'default_marks' => 1, 'is_active' => true]);
        $correct = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Approved barrier controls', 'is_correct' => true, 'display_order' => 1]);
        $wrong = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Unapproved controls', 'is_correct' => false, 'display_order' => 2]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'display_order' => 1, 'points' => 1]);
        $schedule = ExamSchedule::create(['exam_id' => $exam->id, 'group_id' => $group->id, 'training_class_id' => $class->id, 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'duration_minutes' => 60, 'status' => 'scheduled']);

        $failedAttempt = $this->attempt($schedule, $student, 1, 'submitted', $wrong->public_id);
        $passedAttempt = $this->attempt($schedule, $student, 2, 'submitted', $correct->public_id);
        $inProgressAttempt = $this->attempt($schedule, $student, 3, 'in_progress', null);

        return compact('proctor', 'failedAttempt', 'passedAttempt', 'inProgressAttempt');
    }

    private function attempt(ExamSchedule $schedule, User $student, int $number, string $status, ?string $answer): ExamAttempt
    {
        $attempt = ExamAttempt::create(['exam_id' => $schedule->exam_id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $student->id, 'attempt_number' => $number, 'status' => $status, 'started_at' => now()->subMinutes(20), 'expires_at' => now()->addMinutes(40), 'submitted_at' => $status === 'submitted' ? now()->subMinute() : null]);
        ExamAttemptQuestion::create(['exam_attempt_id' => $attempt->id, 'question_id' => Question::firstOrFail()->id, 'display_order' => 1, 'points' => 1, 'answer' => $answer, 'answered_at' => $answer ? now()->subMinutes(2) : null]);

        return $attempt;
    }
}
