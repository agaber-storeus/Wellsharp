<?php

namespace Tests\Feature\Operational;

use App\Actions\Exams\ControlOperationalExamAction;
use App\Enums\ClassStatus;
use App\Models\AuditEvent;
use App\Models\ExamControlCredential;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProctorVerificationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function actingAsInstructor(User $instructor): static
    {
        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version]);

        return $this;
    }

    private function makeClass(User $proctor, User $instructor, array $overrides = []): TrainingClass
    {
        return TrainingClass::factory()->create(array_merge([
            'proctor_id' => $proctor->id,
            'instructor_id' => $instructor->id,
            'status' => ClassStatus::Planned,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ], $overrides));
    }

    public function test_correct_proctor_id_records_succeeded_verification_and_starts_the_class(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), [
            'action' => 'start',
            'proctor_id' => $proctor->examControlCredential->control_id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'action' => 'class.proctor_verification.succeeded',
            'actor_user_id' => $instructor->id,
            'subject_type' => TrainingClass::class,
            'subject_id' => (string) $class->id,
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.manual_start', 'subject_id' => (string) $class->id]);

        $verification = AuditEvent::where('action', 'class.proctor_verification.succeeded')->firstOrFail();
        $this->assertSame('start', $verification->after_state['operation']);
        $this->assertSame($proctor->id, $verification->after_state['verified_proctor_user_id']);
    }

    public function test_correct_proctor_id_records_succeeded_verification_and_ends_the_class(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor, ['status' => ClassStatus::Active, 'actual_started_at' => now()->subHour()]);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), [
            'action' => 'end',
            'proctor_id' => $proctor->examControlCredential->control_id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'action' => 'class.proctor_verification.succeeded',
            'subject_id' => (string) $class->id,
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.manual_end', 'subject_id' => (string) $class->id]);

        $verification = AuditEvent::where('action', 'class.proctor_verification.succeeded')->firstOrFail();
        $this->assertSame('end', $verification->after_state['operation']);
    }

    public function test_unknown_proctor_id_records_failed_verification_with_user_not_found_reason(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => 'PR-DOESNOTEXIST'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proctor_id');

        $event = AuditEvent::where('action', 'class.proctor_verification.failed')->firstOrFail();
        $this->assertSame('user_not_found', $event->after_state['failure_reason']);
        $this->assertSame('verification', $event->after_state['failure_stage']);
        $this->assertSame('start', $event->after_state['operation']);
        $this->assertSame($instructor->id, $event->actor_user_id);
        $this->assertSame((string) $class->id, $event->subject_id);
    }

    public function test_credential_belonging_to_a_non_proctor_records_not_a_proctor_reason(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $otherInstructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);
        $legacyCredential = ExamControlCredential::create(['user_id' => $otherInstructor->id, 'control_id' => 'PR-LEGACY']);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => $legacyCredential->control_id])
            ->assertStatus(422);

        $event = AuditEvent::where('action', 'class.proctor_verification.failed')->firstOrFail();
        $this->assertSame('not_a_proctor', $event->after_state['failure_reason']);
        $this->assertSame('verification', $event->after_state['failure_stage']);
    }

    public function test_inactive_proctor_records_proctor_inactive_reason(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);
        $disabledProctor = User::factory()->proctor()->create(['status' => 'disabled']);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => $disabledProctor->examControlCredential->control_id])
            ->assertStatus(422);

        $event = AuditEvent::where('action', 'class.proctor_verification.failed')->firstOrFail();
        $this->assertSame('proctor_inactive', $event->after_state['failure_reason']);
    }

    public function test_missing_proctor_id_over_http_is_audited_even_though_validation_rejects_the_request(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);
        $this->actingAsInstructor($instructor);

        // No proctor_id at all - ControlExamRequest's own required_if rule
        // rejects this before the controller/action ever runs. The audit
        // event must still exist, and the validation response must stay
        // exactly as before.
        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proctor_id');

        $event = AuditEvent::where('action', 'class.proctor_verification.failed')->firstOrFail();
        $this->assertSame('missing_proctor_id', $event->after_state['failure_reason']);
        $this->assertSame('validation', $event->after_state['failure_stage']);
        $this->assertSame('start', $event->after_state['operation']);
        $this->assertSame($instructor->id, $event->actor_user_id);
        $this->assertSame((string) $class->id, $event->subject_id);
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Planned->value]);
    }

    public function test_blank_proctor_id_over_http_is_audited_the_same_as_missing(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'end', 'proctor_id' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proctor_id');

        $event = AuditEvent::where('action', 'class.proctor_verification.failed')->firstOrFail();
        $this->assertSame('missing_proctor_id', $event->after_state['failure_reason']);
    }

    public function test_missing_proctor_id_records_the_reason_when_the_action_is_invoked_directly(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);

        try {
            app(ControlOperationalExamAction::class)->executeManual($class, 'start', $instructor, null);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException) {
            // expected - the request must still fail normally.
        }

        $event = AuditEvent::where('action', 'class.proctor_verification.failed')->firstOrFail();
        $this->assertSame('missing_proctor_id', $event->after_state['failure_reason']);
        $this->assertSame('validation', $event->after_state['failure_stage']);
    }

    public function test_verification_succeeds_even_though_the_class_operation_cannot_proceed(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor, ['status' => ClassStatus::Active, 'actual_started_at' => now()->subHour()]);
        $this->actingAsInstructor($instructor);

        // The Class is already active, so "start" is a no-op business-wise -
        // but the Proctor ID verification itself still succeeded and must be
        // recorded as such, not folded into a "failed" bucket.
        $this->postJson(route('instructor.classes.exam-control', $class), [
            'action' => 'start',
            'proctor_id' => $proctor->examControlCredential->control_id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', ['action' => 'class.proctor_verification.succeeded', 'subject_id' => (string) $class->id]);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.proctor_verification.failed']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.manual_start', 'subject_id' => (string) $class->id]);
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Active->value]);

        $failure = AuditEvent::where('action', 'class.control_attempt.failed')->firstOrFail();
        $this->assertSame('start', $failure->after_state['operation']);
        $this->assertSame('class_state', $failure->after_state['failure_stage']);
        $this->assertSame('class_already_active', $failure->after_state['failure_reason']);
        $this->assertSame($instructor->id, $failure->actor_user_id);
    }

    public function test_wrong_status_control_attempt_is_audited_for_start(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor, ['status' => ClassStatus::Cancelled]);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), [
            'action' => 'start',
            'proctor_id' => $proctor->examControlCredential->control_id,
        ])->assertStatus(422)->assertJsonValidationErrors('action');

        $this->assertDatabaseHas('audit_events', ['action' => 'class.proctor_verification.succeeded', 'subject_id' => (string) $class->id]);
        $failure = AuditEvent::where('action', 'class.control_attempt.failed')->firstOrFail();
        $this->assertSame('class_state', $failure->after_state['failure_stage']);
        $this->assertSame('class_cancelled', $failure->after_state['failure_reason']);
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Cancelled->value]);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.manual_start']);
    }

    public function test_wrong_status_control_attempt_is_audited_for_end(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor, ['status' => ClassStatus::Planned]);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), [
            'action' => 'end',
            'proctor_id' => $proctor->examControlCredential->control_id,
        ])->assertStatus(422)->assertJsonValidationErrors('action');

        $failure = AuditEvent::where('action', 'class.control_attempt.failed')->firstOrFail();
        $this->assertSame('class_state', $failure->after_state['failure_stage']);
        $this->assertSame('class_not_started', $failure->after_state['failure_reason']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.manual_end']);
    }

    public function test_ending_an_already_completed_class_is_audited_as_a_noop(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor, ['status' => ClassStatus::Completed]);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), [
            'action' => 'end',
            'proctor_id' => $proctor->examControlCredential->control_id,
        ])->assertOk();

        $failure = AuditEvent::where('action', 'class.control_attempt.failed')->firstOrFail();
        $this->assertSame('class_already_completed', $failure->after_state['failure_reason']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.manual_end']);
    }

    public function test_instructor_not_assigned_to_the_class_is_audited_without_weakening_authorization(): void
    {
        $proctor = User::factory()->proctor()->create();
        $assignedInstructor = User::factory()->instructor()->create();
        $otherInstructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $assignedInstructor);

        $this->actingAsInstructor($otherInstructor)
            ->postJson(route('instructor.classes.exam-control', $class), [
                'action' => 'start',
                'proctor_id' => $proctor->examControlCredential->control_id,
            ])->assertForbidden();

        $failure = AuditEvent::where('action', 'class.control_attempt.failed')->firstOrFail();
        $this->assertSame('authorization', $failure->after_state['failure_stage']);
        $this->assertSame('not_assigned_to_class', $failure->after_state['failure_reason']);
        $this->assertSame($otherInstructor->id, $failure->actor_user_id);
        $this->assertSame((string) $class->id, $failure->subject_id);
        // No Proctor verification ran at all - authorization failed first.
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.proctor_verification.succeeded']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.proctor_verification.failed']);
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Planned->value]);
    }

    public function test_failed_verification_does_not_change_class_state(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => 'PR-DOESNOTEXIST'])
            ->assertStatus(422);

        $this->assertDatabaseHas('classes', ['id' => $class->id, 'status' => ClassStatus::Planned->value]);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.manual_start']);
    }

    public function test_raw_proctor_identifier_is_never_stored_in_any_audit_state(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);
        $controlId = $proctor->examControlCredential->control_id;
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => 'WRONG-'.$controlId])->assertStatus(422);
        $this->postJson(route('instructor.classes.exam-control', $class), ['action' => 'start', 'proctor_id' => $controlId])->assertOk();

        foreach (AuditEvent::all() as $event) {
            $payload = json_encode([$event->before_state, $event->after_state, $event->reason]);
            $this->assertStringNotContainsString($controlId, (string) $payload);
            $this->assertStringNotContainsString('WRONG-'.$controlId, (string) $payload);
        }
    }

    public function test_verification_and_class_start_events_share_the_same_correlation_id(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), [
            'action' => 'start',
            'proctor_id' => $proctor->examControlCredential->control_id,
        ])->assertOk();

        $verification = AuditEvent::where('action', 'class.proctor_verification.succeeded')->firstOrFail();
        $start = AuditEvent::where('action', 'class.manual_start')->firstOrFail();

        $this->assertNotNull($verification->correlation_id);
        $this->assertSame($verification->correlation_id, $start->correlation_id);
    }

    public function test_verification_and_control_attempt_failure_share_the_same_correlation_id(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($proctor, $instructor, ['status' => ClassStatus::Active, 'actual_started_at' => now()->subHour()]);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), [
            'action' => 'start',
            'proctor_id' => $proctor->examControlCredential->control_id,
        ])->assertOk();

        $verification = AuditEvent::where('action', 'class.proctor_verification.succeeded')->firstOrFail();
        $failure = AuditEvent::where('action', 'class.control_attempt.failed')->firstOrFail();

        $this->assertNotNull($verification->correlation_id);
        $this->assertSame($verification->correlation_id, $failure->correlation_id);
    }

    public function test_verification_succeeds_for_any_active_proctor_regardless_of_class_assignment(): void
    {
        // Documents the current business rule: an Instructor's dual-control
        // check accepts any active Proctor's ID, not only the Proctor
        // assigned to this specific Class (TrainingClass::proctor_id is not
        // cross-checked against the submitted credential today).
        $assignedProctor = User::factory()->proctor()->create();
        $otherProctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $class = $this->makeClass($assignedProctor, $instructor);
        $this->actingAsInstructor($instructor);

        $this->postJson(route('instructor.classes.exam-control', $class), [
            'action' => 'start',
            'proctor_id' => $otherProctor->examControlCredential->control_id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', ['action' => 'class.proctor_verification.succeeded', 'subject_id' => (string) $class->id]);
    }

    public function test_proctor_self_service_start_does_not_record_a_verification_event(): void
    {
        $proctor = User::factory()->proctor()->create();
        $class = TrainingClass::factory()->create(['proctor_id' => $proctor->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.exam-control', $class), ['action' => 'start'])
            ->assertOk();

        $this->assertDatabaseMissing('audit_events', ['action' => 'class.proctor_verification.succeeded']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.proctor_verification.failed']);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.manual_start']);
    }

    public function test_proctor_self_service_class_state_rejection_is_still_audited(): void
    {
        // The new class_state auditing is shared by execute() regardless of
        // actor role - a Proctor's own repeated/invalid attempt is just as
        // worth investigating as an Instructor's.
        $proctor = User::factory()->proctor()->create();
        $class = TrainingClass::factory()->create(['proctor_id' => $proctor->id, 'status' => ClassStatus::Cancelled]);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.exam-control', $class), ['action' => 'start'])
            ->assertStatus(422);

        $failure = AuditEvent::where('action', 'class.control_attempt.failed')->firstOrFail();
        $this->assertSame('class_cancelled', $failure->after_state['failure_reason']);
        $this->assertSame($proctor->id, $failure->actor_user_id);
    }

    public function test_automatic_transitions_never_generate_control_attempt_or_verification_events(): void
    {
        $class = TrainingClass::factory()->create(['starts_at' => now()->subMinute(), 'ends_at' => now()->addMinute()]);

        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();
        $this->artisan('wellsharp:process-exam-schedules')->assertSuccessful();

        $this->assertDatabaseHas('audit_events', ['action' => 'class.automatic_start']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.control_attempt.failed']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.proctor_verification.succeeded']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.proctor_verification.failed']);
    }
}
