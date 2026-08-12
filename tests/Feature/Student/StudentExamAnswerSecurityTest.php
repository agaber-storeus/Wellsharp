<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExamAnswerSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_save_valid_mcq_answer_clear_it_and_repeat_without_duplicates(): void
    {
        [$student, $attempt, $attemptQuestion, $option] = $this->makeAttempt('mcq');
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);

        $endpoint = route('student.attempts.answers.update', [$attempt, $attemptQuestion]);
        $this->patchJson($endpoint, ['answer' => $option->public_id])
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('answered', true);
        $this->patchJson($endpoint, ['answer' => $option->public_id])->assertOk();
        $this->patchJson($endpoint, ['answer' => ''])->assertOk()->assertJsonPath('answered', false);

        $this->assertDatabaseHas('exam_attempt_questions', ['id' => $attemptQuestion->id, 'answer' => null]);
        $this->assertSame(1, ExamAttemptQuestion::query()->where('exam_attempt_id', $attempt->id)->count());
    }

    public function test_answer_validation_rejects_invalid_mcq_and_oversized_input(): void
    {
        [$student, $attempt, $attemptQuestion] = $this->makeAttempt('mcq');
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);
        $endpoint = route('student.attempts.answers.update', [$attempt, $attemptQuestion]);

        $this->patchJson($endpoint, [])->assertStatus(422);
        $this->patchJson($endpoint, ['answer' => 'not-an-option'])->assertStatus(422);
        $this->patchJson($endpoint, ['answer' => str_repeat('x', 5001)])->assertStatus(422);
        $this->assertDatabaseHas('exam_attempt_questions', ['id' => $attemptQuestion->id, 'answer' => null]);
    }

    public function test_true_false_answers_accept_only_supported_values(): void
    {
        [$student, $attempt, $attemptQuestion] = $this->makeAttempt('true_false');
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);
        $endpoint = route('student.attempts.answers.update', [$attempt, $attemptQuestion]);

        $this->patchJson($endpoint, ['answer' => 'true'])->assertOk();
        $this->patchJson($endpoint, ['answer' => 'maybe'])->assertStatus(422);
        $this->assertDatabaseHas('exam_attempt_questions', ['id' => $attemptQuestion->id, 'answer' => 'true']);
    }

    public function test_only_attempt_owner_can_view_or_save_and_student_role_is_required(): void
    {
        [$student, $attempt, $attemptQuestion, $option] = $this->makeAttempt('mcq');
        $otherStudent = User::factory()->student()->create();
        $proctor = User::factory()->proctor()->create();
        $endpoint = route('student.attempts.answers.update', [$attempt, $attemptQuestion]);

        $this->get(route('student.attempts.show', $attempt))->assertRedirect(route('login'));
        $this->actingAs($otherStudent)->withSession(['auth.session_version' => $otherStudent->session_version])
            ->get(route('student.attempts.show', $attempt))->assertForbidden();
        $this->actingAs($otherStudent)->withSession(['auth.session_version' => $otherStudent->session_version])
            ->patchJson($endpoint, ['answer' => $option->public_id])->assertForbidden();
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->patchJson($endpoint, ['answer' => $option->public_id])->assertForbidden();
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])
            ->patchJson(route('student.attempts.answers.update', [$attempt, 999999]), ['answer' => $option->public_id])
            ->assertNotFound();
        $this->assertDatabaseHas('exam_attempt_questions', ['id' => $attemptQuestion->id, 'answer' => null]);
    }

    public function test_expired_or_submitted_attempts_cannot_accept_answers_and_expiry_is_persisted(): void
    {
        [$student, $attempt, $attemptQuestion, $option] = $this->makeAttempt('mcq');
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);
        $endpoint = route('student.attempts.answers.update', [$attempt, $attemptQuestion]);

        $attempt->update(['status' => 'submitted']);
        $this->patchJson($endpoint, ['answer' => $option->public_id])->assertStatus(422);

        $attempt->update(['status' => 'in_progress', 'expires_at' => now()->subMinute()]);
        $this->patchJson($endpoint, ['answer' => $option->public_id])->assertStatus(422);
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'status' => 'expired']);
    }

    public function test_student_cannot_render_a_submitted_or_expired_attempt(): void
    {
        [$student, $attempt] = $this->makeAttempt('mcq');
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);

        $attempt->update(['status' => 'submitted']);
        $this->get(route('student.attempts.show', $attempt))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('status', 'This exam attempt has already been submitted.');

        $attempt->update(['status' => 'in_progress', 'expires_at' => now()->subMinute()]);
        $this->get(route('student.attempts.show', $attempt))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('status', 'This exam attempt expired and can no longer be continued.');
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'status' => 'expired']);
    }

    public function test_expire_endpoint_closes_an_expired_attempt_idempotently(): void
    {
        [$student, $attempt] = $this->makeAttempt('mcq');
        $attempt->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);

        $this->postJson(route('student.attempts.expire', $attempt))
            ->assertOk()
            ->assertJsonPath('expired', true);
        $this->postJson(route('student.attempts.expire', $attempt))
            ->assertOk()
            ->assertJsonPath('expired', true);
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'status' => 'expired']);
    }

    /** @return array{0: User, 1: ExamAttempt, 2: ExamAttemptQuestion, 3?: QuestionOption} */
    private function makeAttempt(string $type): array
    {
        $this->seedRoles();
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();
        $group = Group::create(['name' => 'Answer Security Group', 'status' => 'active']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Answer Security Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $question = Question::create(['course_id' => $course->id, 'question_text' => 'Answer security question', 'type' => $type, 'difficulty' => 'easy', 'correct_answer_text' => $type === 'input' ? 'answer' : null]);
        $option = $type === 'mcq' ? QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Correct-looking option', 'is_correct' => true, 'display_order' => 1]) : null;
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'display_order' => 1, 'points' => 1]);
        $schedule = ExamSchedule::create(['exam_id' => $exam->id, 'group_id' => $group->id, 'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'duration_minutes' => 60, 'status' => 'scheduled']);
        $attempt = ExamAttempt::create(['exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $student->id, 'attempt_number' => 1, 'status' => 'in_progress', 'started_at' => now(), 'expires_at' => now()->addHour()]);
        $attemptQuestion = ExamAttemptQuestion::create(['exam_attempt_id' => $attempt->id, 'question_id' => $question->id, 'display_order' => 1, 'points' => 1]);

        return array_values(array_filter([$student, $attempt, $attemptQuestion, $option], fn ($value): bool => $value !== null));
    }
}
