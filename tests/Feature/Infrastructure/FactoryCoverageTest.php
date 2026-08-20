<?php

namespace Tests\Feature\Infrastructure;

use App\Enums\CertificateDocumentType;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamQuestionOrderMode;
use App\Enums\GroupMembershipStatus;
use App\Enums\QuestionType;
use App\Models\AuditEvent;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\ClassStaffAssignment;
use App\Models\CourseLevel;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamControlCredential;
use App\Models\ExamGroupAssignment;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Language;
use App\Models\LoginEvent;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Stack;
use App\Models\Supplement;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises every model factory added to close the gap where ~25 models had
 * no factory and tests fell back to raw Model::create(). Each assertion
 * confirms the factory's default state satisfies the model's real schema
 * constraints (NOT NULL columns, unique indexes) without duplicating any
 * business generator logic.
 */
class FactoryCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_reference_data_factories_produce_valid_unique_rows(): void
    {
        $this->assertNotNull(CourseLevel::factory()->create()->slug);
        $this->assertNotNull(Stack::factory()->inactive()->create()->id);
        $this->assertNotNull(Supplement::factory()->create()->id);
        $this->assertNotNull(Language::factory()->create()->id);
    }

    public function test_role_and_role_assignment_factories(): void
    {
        $assignment = RoleAssignment::factory()->create();
        $this->assertNull($assignment->ended_at);
        $ended = RoleAssignment::factory()->ended()->create();
        $this->assertNotNull($ended->ended_at);
    }

    public function test_group_and_membership_factories_link_correctly(): void
    {
        $group = Group::factory()->create();
        $membership = GroupMembership::factory()->create(['group_id' => $group->id]);
        $this->assertSame(GroupMembershipStatus::Active, $membership->status);
        $this->assertTrue($group->students->contains($membership->student_user_id));

        $removed = GroupMembership::factory()->removed()->create();
        $this->assertSame(GroupMembershipStatus::Removed, $removed->status);
        $this->assertNotNull($removed->removed_at);
    }

    public function test_question_factory_covers_every_type_with_a_generated_code(): void
    {
        $mcq = Question::factory()->withOptions()->create();
        $this->assertSame(QuestionType::Mcq, $mcq->type);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}$/', $mcq->code);
        $this->assertCount(4, $mcq->options);
        $this->assertSame(1, $mcq->options->where('is_correct', true)->count());

        $trueFalse = Question::factory()->trueFalse(true)->create();
        $this->assertSame(QuestionType::TrueFalse, $trueFalse->type);
        $this->assertTrue($trueFalse->correct_answer_boolean);

        $input = Question::factory()->input('Expected answer')->create();
        $this->assertSame(QuestionType::Input, $input->type);
        $this->assertSame('Expected answer', $input->correct_answer_text);

        $inactive = Question::factory()->inactive()->create();
        $this->assertFalse($inactive->is_active);
    }

    public function test_question_option_factory_attaches_to_a_mcq_question(): void
    {
        $option = QuestionOption::factory()->correct()->create();
        $this->assertSame(QuestionType::Mcq, $option->question->type);
        $this->assertTrue($option->is_correct);
    }

    public function test_exam_and_exam_question_factories(): void
    {
        $exam = Exam::factory()->published()->shuffle()->create();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5,8}$/', $exam->code);
        $this->assertSame(ExamQuestionOrderMode::Shuffle, $exam->question_order_mode);

        $examQuestion = ExamQuestion::factory()->create(['exam_id' => $exam->id]);
        $this->assertSame($exam->id, $examQuestion->exam_id);

        $assignment = ExamGroupAssignment::factory()->removed()->create();
        $this->assertNotNull($assignment->removed_at);
    }

    public function test_exam_schedule_states_produce_expected_windows(): void
    {
        $upcoming = ExamSchedule::factory()->upcoming()->create();
        $this->assertTrue($upcoming->start_date->isFuture());

        $completed = ExamSchedule::factory()->completed()->create();
        $this->assertTrue($completed->end_date->isPast());
    }

    public function test_exam_attempt_factory_stays_consistent_with_its_schedule(): void
    {
        $attempt = ExamAttempt::factory()->submitted(true, 92.5)->create();
        $schedule = ExamSchedule::query()->findOrFail($attempt->exam_schedule_id);

        $this->assertSame($schedule->exam_id, $attempt->exam_id);
        $this->assertSame(ExamAttemptStatus::Submitted, $attempt->status);
        $this->assertTrue($attempt->passed);

        $expired = ExamAttempt::factory()->expired()->create();
        $this->assertSame(ExamAttemptStatus::Expired, $expired->status);

        $attemptQuestion = ExamAttemptQuestion::factory()->answered('true')->create(['exam_attempt_id' => $attempt->id]);
        $this->assertSame('true', $attemptQuestion->answer);
        $this->assertNotNull($attemptQuestion->answered_at);
    }

    public function test_exam_control_credential_factory_defaults_to_a_non_colliding_admin_owner(): void
    {
        $credential = ExamControlCredential::factory()->create();
        $this->assertTrue($credential->user->hasRole(Role::ADMIN));

        // Attaching one to a real Proctor (created without the factory's
        // automatic credential, since eligibleForRole is Proctor-only) must
        // not collide with the unique(user_id) constraint.
        $proctor = User::factory()->proctor()->create();
        $proctor->examControlCredential()->delete();
        $manual = ExamControlCredential::factory()->create(['user_id' => $proctor->id]);
        $this->assertSame($proctor->id, $manual->user_id);
    }

    public function test_certificate_factory_stays_consistent_with_its_attempt_and_supports_revocation(): void
    {
        $certificate = Certificate::factory()->create();
        $attempt = ExamAttempt::query()->findOrFail($certificate->exam_attempt_id);

        $this->assertSame($attempt->student_user_id, $certificate->student_user_id);
        $this->assertSame($attempt->exam_id, $certificate->exam_id);
        $this->assertSame($attempt->exam_schedule_id, $certificate->exam_schedule_id);

        $revoked = Certificate::factory()->revoked()->create();
        $this->assertNotNull($revoked->revoked_at);
        $this->assertNotNull($revoked->revocation_reason);
    }

    public function test_certificate_document_factory_covers_all_three_document_types_for_one_certificate(): void
    {
        $certificate = Certificate::factory()->create();
        CertificateDocument::factory()->create(['certificate_id' => $certificate->id]);
        CertificateDocument::factory()->completionCardFront()->create(['certificate_id' => $certificate->id]);
        CertificateDocument::factory()->completionCardBack()->create(['certificate_id' => $certificate->id]);

        $types = $certificate->documents()->pluck('type')->map(fn (CertificateDocumentType $type) => $type->value)->sort()->values()->all();
        $this->assertSame(['completion_card_back', 'completion_card_front', 'knowledge_assessment_report'], $types);
    }

    public function test_audit_and_login_event_factories(): void
    {
        $this->assertNotNull(AuditEvent::factory()->create()->occurred_at);

        $success = LoginEvent::factory()->create();
        $this->assertSame('success', $success->outcome);

        $failed = LoginEvent::factory()->failed()->create();
        $this->assertNull($failed->user_id);
        $this->assertSame('invalid_credentials', $failed->outcome);
    }

    public function test_training_provider_class_enrollment_and_staff_assignment_states(): void
    {
        $this->assertSame('archived', TrainingProvider::factory()->archived()->create()->status->value);
        $this->assertSame('inactive', TrainingProvider::factory()->inactive()->create()->status->value);

        $completedClass = TrainingClass::factory()->completed()->create();
        $this->assertNotNull($completedClass->actual_started_at);
        $this->assertNotNull($completedClass->actual_ended_at);

        $enrollment = Enrollment::factory()->withSkillsScore(88)->create();
        $this->assertSame(88, $enrollment->skills_score);
        $this->assertSame('withdrawn', Enrollment::factory()->withdrawn()->create()->status->value);

        $instructorAssignment = ClassStaffAssignment::factory()->instructor()->create();
        $this->assertTrue($instructorAssignment->user->hasRole(Role::INSTRUCTOR));
        $this->assertSame('ended', ClassStaffAssignment::factory()->ended()->create()->status->value);
    }
}
