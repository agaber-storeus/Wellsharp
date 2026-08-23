<?php

namespace Tests\Feature\Operational;

use App\Enums\ClassStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamScheduleStatus;
use App\Enums\GroupMembershipStatus;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamControlCredential;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\StudentSurvey;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_only_proctors_receive_a_unique_proctors_id(): void
    {
        $first = User::factory()->proctor()->create();
        $second = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5,8}$/', $first->examControlCredential->control_id);
        $this->assertNotSame($first->examControlCredential->control_id, $second->examControlCredential->control_id);
        $this->assertNull($instructor->examControlCredential);
        $this->assertNull($admin->examControlCredential);
        $this->assertNull($student->examControlCredential);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('exam_control_credentials', 2);
    }

    public function test_instructor_can_use_an_active_proctor_id_to_start_and_end_a_linked_class(): void
    {
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create([
            'course_id' => $course->id,
            'status' => ClassStatus::Planned,
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addDays(2),
        ]);
        $group = Group::create(['name' => 'Control Group', 'status' => 'active']);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Controlled Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $question = Question::create([
            'course_id' => $course->id,
            'question_text' => 'Control question',
            'type' => 'input',
            'difficulty' => 'easy',
            'correct_answer_text' => 'answer',
        ]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'display_order' => 1, 'points' => 1]);
        $schedule = ExamSchedule::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'training_class_id' => $trainingClass->id,
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDays(2)->toDateString(),
            'duration_minutes' => 60,
            'status' => ExamScheduleStatus::Scheduled,
        ]);
        $student = User::factory()->student()->create();
        GroupMembership::create([
            'group_id' => $group->id,
            'student_user_id' => $student->id,
            'status' => GroupMembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        StudentSurvey::create([
            'student_user_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => 'completed',
            'contact_confirmed_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version]);
        $controlId = $proctor->examControlCredential->control_id;
        $this->postJson(route('instructor.proctor-id.verify'), ['proctor_id' => $controlId])
            ->assertOk()
            ->assertJsonPath('proctor_name', $proctor->display_name);

        $this->postJson(route('instructor.classes.exam-control', $trainingClass), [
            'action' => 'start',
            'proctor_id' => $controlId,
        ])->assertOk()->assertJsonPath('class.status', 'active');

        $this->assertDatabaseHas('classes', ['id' => $trainingClass->id, 'status' => ClassStatus::Active->value]);
        $this->assertNotNull($trainingClass->fresh()->actual_started_at);
        $this->assertDatabaseHas('exam_schedules', ['id' => $schedule->id, 'override_started_by_user_id' => $instructor->id]);

        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);
        $this->post(route('student.exams.start', $schedule))->assertRedirect();
        $attempt = $schedule->attempts()->firstOrFail();
        $this->assertSame(ExamAttemptStatus::InProgress, $attempt->status);

        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version]);
        $this->postJson(route('instructor.classes.exam-control', $trainingClass), [
            'action' => 'end',
            'proctor_id' => $controlId,
        ])->assertOk()->assertJsonPath('class.status', 'completed');

        $this->assertDatabaseHas('classes', ['id' => $trainingClass->id, 'status' => ClassStatus::Completed->value]);
        $this->assertNotNull($trainingClass->fresh()->actual_ended_at);
        $this->assertDatabaseHas('exam_schedules', ['id' => $schedule->id, 'status' => ExamScheduleStatus::Completed->value, 'override_ended_by_user_id' => $instructor->id]);
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'status' => ExamAttemptStatus::Submitted->value]);
    }

    public function test_instructor_does_not_own_a_proctors_id(): void
    {
        $instructor = User::factory()->instructor()->create();

        $this->assertNull($instructor->examControlCredential);
        $this->assertDatabaseMissing('exam_control_credentials', ['user_id' => $instructor->id]);
    }

    public function test_legacy_non_proctor_owned_credentials_never_verify(): void
    {
        $class = TrainingClass::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $instructor = User::factory()->instructor()->create();
        $otherInstructor = User::factory()->instructor()->create();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        // Simulate rows that should never exist under the current rule (e.g. left over
        // from before generation was restricted to Proctors, or inserted directly).
        // The verifier must reject them purely on the owner's current role, not on
        // whether a credential row happens to exist.
        $legacyInstructorCredential = ExamControlCredential::create(['user_id' => $otherInstructor->id, 'control_id' => 'PR-LEGACY-INSTRUCTOR']);
        $legacyAdminCredential = ExamControlCredential::create(['user_id' => $admin->id, 'control_id' => 'PR-LEGACY-ADMIN']);
        $legacyStudentCredential = ExamControlCredential::create(['user_id' => $student->id, 'control_id' => 'PR-LEGACY-STUDENT']);

        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version]);

        foreach ([$legacyInstructorCredential, $legacyAdminCredential, $legacyStudentCredential] as $credential) {
            $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => $credential->control_id])
                ->assertStatus(422)
                ->assertJsonValidationErrors('proctor_id');
        }

        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Planned->value]);
    }

    public function test_instructor_cannot_use_an_unknown_or_disabled_proctors_credential(): void
    {
        $class = TrainingClass::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $instructor = User::factory()->instructor()->create();
        $disabledProctor = User::factory()->proctor()->create(['status' => 'disabled']);

        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version]);

        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => 'PR-DOESNOTEXIST'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proctor_id');

        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => $disabledProctor->examControlCredential->control_id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proctor_id');

        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Planned->value]);
    }

    public function test_instructor_must_provide_a_proctor_id(): void
    {
        $class = TrainingClass::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $instructor = User::factory()->instructor()->create();

        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version])
            ->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proctor_id');
    }

    public function test_proctor_can_start_and_end_directly_with_no_credential(): void
    {
        $class = TrainingClass::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $proctor = User::factory()->proctor()->create();

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.exam-control', $class), ['action' => 'start'])
            ->assertOk()
            ->assertJsonPath('class.status', ClassStatus::Active->value);

        $this->postJson(route('proctor.classes.exam-control', $class), ['action' => 'end'])
            ->assertOk()
            ->assertJsonPath('class.status', ClassStatus::Completed->value);

        $this->assertDatabaseHas('audit_events', ['action' => 'class.manual_start', 'actor_user_id' => $proctor->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.manual_end', 'actor_user_id' => $proctor->id]);
    }

    public function test_student_cannot_control_a_class_even_with_a_valid_credential(): void
    {
        $class = TrainingClass::factory()->create();
        $proctor = User::factory()->proctor()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])
            ->postJson(route('proctor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => $proctor->examControlCredential->control_id])
            ->assertForbidden();
    }

    public function test_automatic_transitions_are_schedule_gated_and_idempotent(): void
    {
        $class = TrainingClass::factory()->create(['starts_at' => now()->subMinute(), 'ends_at' => now()->addMinute()]);

        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Active->value]);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.automatic_start']);

        $class->update(['ends_at' => now()->subMinute()]);
        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Completed->value]);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.automatic_end']);

        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();
        $this->assertSame(1, AuditEvent::where('action', 'class.automatic_start')->count());
        $this->assertSame(1, AuditEvent::where('action', 'class.automatic_end')->count());
    }

    public function test_visiting_the_proctor_dashboard_auto_transitions_a_due_class_without_the_scheduler(): void
    {
        // Reproduces the scenario where `wellsharp:process-exam-schedules` never
        // ran (no cron/schedule:work configured): a Class whose starts_at has
        // already arrived must not stay "planned" forever just because nobody
        // visited it before the request that finally loads the dashboard.
        $class = TrainingClass::factory()->create(['starts_at' => now()->subMinute(), 'ends_at' => now()->addDay()]);
        $proctor = User::factory()->proctor()->create();

        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Planned->value]);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.dashboard'))
            ->assertOk();

        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Active->value]);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.automatic_start']);
    }

    public function test_visiting_the_instructor_dashboard_auto_transitions_a_due_class_without_the_scheduler(): void
    {
        $class = TrainingClass::factory()->active()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->subMinute()]);
        $instructor = User::factory()->instructor()->create();

        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version])
            ->get(route('instructor.dashboard'))
            ->assertOk();

        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Completed->value]);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.automatic_end']);
    }

    public function test_process_command_leaves_future_classes_planned(): void
    {
        $class = TrainingClass::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();

        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Planned->value]);
        $this->assertDatabaseMissing('audit_events', ['subject_id' => (string) $class->id, 'action' => 'class.automatic_start']);
    }

    public function test_repeated_command_execution_does_not_create_duplicate_classes_or_audit_events(): void
    {
        $class = TrainingClass::factory()->create(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);

        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();
        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();
        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();

        $this->assertSame(1, TrainingClass::where('id', $class->id)->count());
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Active->value]);
        $this->assertSame(1, AuditEvent::where('action', 'class.automatic_start')->where('subject_id', (string) $class->id)->count());
    }

    public function test_process_command_only_transitions_eligible_classes_and_leaves_others_untouched(): void
    {
        $dueClass = TrainingClass::factory()->create(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        $futureClass = TrainingClass::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $alreadyActiveClass = TrainingClass::factory()->create([
            'status' => ClassStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'actual_started_at' => now()->subHour(),
        ]);
        $cancelledClass = TrainingClass::factory()->create([
            'status' => ClassStatus::Cancelled,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subHour(),
        ]);

        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();

        $this->assertDatabaseHas('classes', ['id' => $dueClass->id, 'status' => ClassStatus::Active->value]);
        $this->assertDatabaseHas('classes', ['id' => $futureClass->id, 'status' => ClassStatus::Planned->value]);
        $this->assertDatabaseHas('classes', ['id' => $alreadyActiveClass->id, 'status' => ClassStatus::Active->value]);
        $this->assertDatabaseHas('classes', ['id' => $cancelledClass->id, 'status' => ClassStatus::Cancelled->value]);
        $this->assertSame(1, AuditEvent::where('action', 'class.automatic_start')->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'class.automatic_start', 'subject_id' => (string) $dueClass->id]);
    }

    public function test_cancelled_class_cannot_be_started(): void
    {
        $class = TrainingClass::factory()->create(['status' => ClassStatus::Cancelled, 'starts_at' => now()->subMinute()]);
        $proctor = User::factory()->proctor()->create();

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.exam-control', $class), ['action' => 'start'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('action');
    }

    public function test_repeated_manual_transitions_are_idempotent(): void
    {
        $class = TrainingClass::factory()->create();
        $proctor = User::factory()->proctor()->create();
        $payload = ['action' => 'start'];

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.exam-control', $class), $payload)
            ->assertOk();
        $this->postJson(route('proctor.classes.exam-control', $class), $payload)
            ->assertOk();
        $this->assertSame(1, AuditEvent::where('action', 'class.manual_start')->count());

        $end = ['action' => 'end'];
        $this->postJson(route('proctor.classes.exam-control', $class), $end)->assertOk();
        $this->postJson(route('proctor.classes.exam-control', $class), $end)->assertOk();
        $this->assertSame(1, AuditEvent::where('action', 'class.manual_end')->count());
    }
}
