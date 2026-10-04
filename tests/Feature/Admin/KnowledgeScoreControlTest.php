<?php

namespace Tests\Feature\Admin;

use App\Actions\Certificates\IssueCertificateAction;
use App\Actions\Exams\ManageKnowledgeScoreControlAction;
use App\Enums\KnowledgeControlType;
use App\Models\AuditEvent;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\TrainingClass;
use App\Models\User;
use App\Services\KnowledgeResultService;
use App\Services\AdminDashboardService;
use App\Services\OperationalClassMapPointBuilder;
use App\Services\OperationalReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KnowledgeScoreControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_control_and_precedence_resolve_through_one_canonical_result(): void
    {
        $data = $this->fixture();
        $action = app(ManageKnowledgeScoreControlAction::class);
        $admin = $data['admin'];

        $questionControl = $action->create($data['attempt'], KnowledgeControlType::QuestionPoints, [
            'exam_attempt_question_id' => $data['wrongAttemptQuestion']->id,
            'numeric_value' => 5,
            'reason' => 'Manual review awards full credit.',
        ], $admin);
        $action->create($data['attempt'], KnowledgeControlType::ScoreAdjustment, ['numeric_value' => -10, 'reason' => 'Apply approved ten point deduction.'], $admin);
        $finalControl = $action->create($data['attempt'], KnowledgeControlType::FinalScore, ['numeric_value' => 65, 'reason' => 'Committee final score decision.'], $admin);
        $passControl = $action->create($data['attempt'], KnowledgeControlType::PassFail, ['boolean_value' => true, 'reason' => 'Committee grants passing decision.'], $admin);

        $result = app(KnowledgeResultService::class)->resolve($data['attempt']->fresh());
        $this->assertSame(50.0, $result['calculated_score']);
        $this->assertSame(100.0, $result['question_adjusted_score']);
        $this->assertSame(-10.0, $result['adjustment_total']);
        $this->assertSame(90.0, $result['score_before_final_override']);
        $this->assertSame(65.0, $result['final_score_override']);
        $this->assertSame(65.0, $result['final_knowledge_score']);
        $this->assertFalse($result['calculated_passed']);
        $this->assertTrue($result['final_passed']);
        $this->assertTrue($result['certificate_eligible']);
        $this->assertCount(4, $result['active_overrides']);
        $this->assertEquals(50.0, $questionControl->fresh()->before_state['final_knowledge_score']);
        $this->assertEquals(100.0, $questionControl->fresh()->after_state['final_knowledge_score']);

        $action->revert($data['attempt'], $passControl, 'Pass decision withdrawn.', $admin);
        $this->assertFalse(app(KnowledgeResultService::class)->resolve($data['attempt']->fresh())['final_passed']);
        $action->revert($data['attempt'], $finalControl, 'Final score decision withdrawn.', $admin);
        $this->assertSame(90.0, app(KnowledgeResultService::class)->resolve($data['attempt']->fresh())['final_knowledge_score']);
        $action->revert($data['attempt'], $questionControl, 'Question award withdrawn.', $admin);
    }

    public function test_question_override_enforces_points_boundary_and_conflict_rules(): void
    {
        $data = $this->fixture();
        $action = app(ManageKnowledgeScoreControlAction::class);
        $payload = ['exam_attempt_question_id' => $data['wrongAttemptQuestion']->id, 'numeric_value' => 6, 'reason' => 'Invalid excess award.'];

        try {
            $action->create($data['attempt'], KnowledgeControlType::QuestionPoints, $payload, $data['admin']);
            $this->fail('Expected points validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('numeric_value', $exception->errors());
        }

        $payload['numeric_value'] = 4;
        $action->create($data['attempt'], KnowledgeControlType::QuestionPoints, $payload, $data['admin']);
        $this->expectException(ValidationException::class);
        $action->create($data['attempt'], KnowledgeControlType::QuestionPoints, $payload, $data['admin']);
    }

    public function test_restore_reverts_history_without_deleting_and_audits_every_change(): void
    {
        $data = $this->fixture();
        $action = app(ManageKnowledgeScoreControlAction::class);
        $action->create($data['attempt'], KnowledgeControlType::ScoreAdjustment, ['numeric_value' => 12, 'reason' => 'Approved adjustment.'], $data['admin']);
        $action->create($data['attempt'], KnowledgeControlType::FinalScore, ['numeric_value' => 88, 'reason' => 'Approved final score.'], $data['admin']);

        $this->assertSame(2, $action->restore($data['attempt'], 'Restore fully calculated result.', $data['admin']));
        $result = app(KnowledgeResultService::class)->resolve($data['attempt']->fresh());
        $this->assertSame(50.0, $result['final_knowledge_score']);
        $this->assertCount(0, $result['active_overrides']);
        $this->assertCount(2, $result['override_history']);
        $this->assertDatabaseCount('exam_attempt_score_controls', 2);
        $this->assertSame(3, AuditEvent::query()->where('subject_type', ExamAttempt::class)->count());
    }

    public function test_skills_score_is_irrelevant_and_fail_to_pass_can_be_explicitly_issued(): void
    {
        $data = $this->fixture();
        Enrollment::factory()->create(['class_id' => $data['class']->id, 'student_user_id' => $data['student']->id, 'skills_score' => 100]);
        $this->assertNull(app(IssueCertificateAction::class)->execute($data['attempt']));

        app(ManageKnowledgeScoreControlAction::class)->create($data['attempt'], KnowledgeControlType::FinalScore, ['numeric_value' => 80, 'reason' => 'Approved final Knowledge score.'], $data['admin']);
        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']->fresh());
        $this->assertNotNull($certificate);
        $this->assertSame(80.0, (float) $certificate->score);
        $this->assertSame(50.0, (float) $data['attempt']->fresh()->score);
    }

    public function test_pass_to_fail_never_auto_revokes_existing_certificate(): void
    {
        $data = $this->fixture(calculatedScore: 100, passed: true);
        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);
        app(ManageKnowledgeScoreControlAction::class)->create($data['attempt'], KnowledgeControlType::PassFail, ['boolean_value' => false, 'reason' => 'Administrative failure decision.'], $data['admin']);

        $this->assertFalse(app(KnowledgeResultService::class)->resolve($data['attempt']->fresh())['certificate_eligible']);
        $this->assertNull(app(IssueCertificateAction::class)->execute($data['attempt']->fresh()));
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'issued', 'score' => 100]);
    }

    public function test_admin_endpoints_require_admin_reason_and_render_controls(): void
    {
        $data = $this->fixture();
        $certificate = Certificate::factory()->create(['exam_attempt_id' => $data['attempt']->id, 'exam_id' => $data['attempt']->exam_id, 'exam_schedule_id' => $data['attempt']->exam_schedule_id, 'student_user_id' => $data['student']->id]);

        $this->actingAs($data['admin'])->withSession(['auth.session_version' => $data['admin']->session_version])
            ->post(route('admin.certificates.knowledge-controls.store', $certificate), ['type' => 'score_adjustment', 'numeric_value' => 5])
            ->assertSessionHasErrors('reason');
        $this->post(route('admin.certificates.knowledge-controls.store', $certificate), ['type' => 'score_adjustment', 'numeric_value' => 5, 'reason' => 'Approved review adjustment.'])
            ->assertRedirect();
        $this->get(route('admin.certificates.show', $certificate))->assertOk()->assertSee('Knowledge Score Control')->assertSee('Approved review adjustment.');

        $student = $data['student'];
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])
            ->post(route('admin.certificates.knowledge-controls.store', $certificate), ['type' => 'score_adjustment', 'numeric_value' => 5, 'reason' => 'Unauthorized change.'])
            ->assertForbidden();
    }

    public function test_major_consumers_share_the_same_canonical_result(): void
    {
        $data = $this->fixture();
        $proctor = User::factory()->proctor()->create();
        $data['class']->update(['proctor_id' => $proctor->id]);
        $enrollment = Enrollment::factory()->create(['class_id' => $data['class']->id, 'student_user_id' => $data['student']->id, 'skills_score' => 5]);
        app(ManageKnowledgeScoreControlAction::class)->create($data['attempt'], KnowledgeControlType::FinalScore, ['numeric_value' => 82, 'reason' => 'Shared result verification.'], $data['admin']);

        $canonical = app(KnowledgeResultService::class)->resolve($data['attempt']->fresh());
        $this->actingAs($proctor);
        $roster = app(OperationalClassMapPointBuilder::class)->scoreRowForEnrollment($enrollment);
        $reports = app(OperationalReportingService::class);
        $reportedAttempt = $reports->allAttempts($reports->accessibleClasses($proctor))->firstWhere('id', $data['attempt']->id);
        $dashboard = app(AdminDashboardService::class)->build();
        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']->fresh());

        $this->assertSame(82.0, $canonical['final_knowledge_score']);
        $this->assertSame('82.00', $roster['score']);
        $this->assertSame(82.0, (float) $reportedAttempt->canonical_knowledge_score);
        $this->assertSame(82.0, $dashboard['exam_performance']['average_knowledge_score']);
        $this->assertSame(82.0, (float) $certificate->score);
        $this->assertTrue($roster['passed']);
        $this->assertTrue($reportedAttempt->canonical_knowledge_passed);
    }

    /** @return array<string, mixed> */
    private function fixture(float $calculatedScore = 50, bool $passed = false): array
    {
        $this->seedRoles();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();
        $class = TrainingClass::factory()->create(['course_id' => $course->id]);
        $group = Group::factory()->create();
        $exam = Exam::factory()->published()->create(['course_id' => $course->id, 'passing_score' => 70]);
        $schedule = ExamSchedule::factory()->create(['exam_id' => $exam->id, 'training_class_id' => $class->id, 'group_id' => $group->id]);
        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $student->id, 'status' => 'submitted', 'score' => $calculatedScore, 'passed' => $passed, 'submitted_at' => now()]);

        $correctQuestion = Question::factory()->mcq()->create(['course_id' => $course->id]);
        $correctOption = QuestionOption::factory()->correct()->create(['question_id' => $correctQuestion->id, 'display_order' => 1]);
        $correctAttemptQuestion = ExamAttemptQuestion::factory()->create(['exam_attempt_id' => $attempt->id, 'question_id' => $correctQuestion->id, 'display_order' => 1, 'points' => 5, 'answer' => $correctOption->public_id]);
        $wrongQuestion = Question::factory()->mcq()->create(['course_id' => $course->id]);
        QuestionOption::factory()->correct()->create(['question_id' => $wrongQuestion->id, 'display_order' => 1]);
        $wrongOption = QuestionOption::factory()->create(['question_id' => $wrongQuestion->id, 'display_order' => 2]);
        $wrongAttemptQuestion = ExamAttemptQuestion::factory()->create(['exam_attempt_id' => $attempt->id, 'question_id' => $wrongQuestion->id, 'display_order' => 2, 'points' => 5, 'answer' => $wrongOption->public_id]);

        return compact('admin', 'student', 'class', 'attempt', 'correctAttemptQuestion', 'wrongAttemptQuestion');
    }
}
