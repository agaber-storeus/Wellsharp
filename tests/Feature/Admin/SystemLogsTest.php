<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\LoginEvent;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_audit_events_are_listed(): void
    {
        AuditEvent::factory()->create(['action' => 'course.created', 'actor_user_id' => $this->admin->id]);

        $this->getJson(route('admin.system-logs.data'))
            ->assertOk()
            ->assertJsonFragment(['label' => 'Course created']);
    }

    public function test_automatic_system_events_display_a_safe_system_actor_and_result(): void
    {
        AuditEvent::factory()->create(['action' => 'class.automatic_start', 'actor_user_id' => null]);

        $response = $this->getJson(route('admin.system-logs.data'))->assertOk();
        $row = collect($response->json('data'))->firstWhere('label', 'Class started automatically');

        $this->assertNotNull($row);
        $this->assertSame('System', $row['actor']);
        $this->assertSame('system', $row['result']);
    }

    public function test_pagination_bounds_results_per_page(): void
    {
        for ($i = 0; $i < 30; $i++) {
            AuditEvent::factory()->create(['action' => 'course.created', 'occurred_at' => now()->subMinutes($i)]);
        }

        $this->getJson(route('admin.system-logs.data'))
            ->assertOk()
            ->assertJsonPath('meta.total', 30)
            ->assertJsonCount(25, 'data');

        $this->getJson(route('admin.system-logs.data', ['page' => 2]))
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_filtering_by_category_only_returns_matching_events(): void
    {
        AuditEvent::factory()->create(['action' => 'course.created']);
        AuditEvent::factory()->create(['action' => 'user.created']);

        $response = $this->getJson(route('admin.system-logs.data', ['category' => 'courses']))->assertOk();
        $labels = collect($response->json('data'))->pluck('label');

        $this->assertTrue($labels->contains('Course created'));
        $this->assertFalse($labels->contains('User created'));
    }

    public function test_filtering_by_success_result_includes_ordinary_business_actions(): void
    {
        // Regression guard: ordinary create/update actions never carry an
        // explicit "result", and must still count as a Success, not be
        // silently excluded from the "Success" filter.
        AuditEvent::factory()->create(['action' => 'course.created']);
        AuditEvent::factory()->create(['action' => 'class.proctor_verification.failed', 'after_state' => ['operation' => 'start', 'failure_stage' => 'verification', 'failure_reason' => 'user_not_found']]);

        $response = $this->getJson(route('admin.system-logs.data', ['result' => 'success']))->assertOk();
        $labels = collect($response->json('data'))->pluck('label');

        $this->assertTrue($labels->contains('Course created'));
        $this->assertFalse($labels->contains('Proctor verification failed'));
    }

    public function test_filtering_by_failed_result_only_returns_failed_events(): void
    {
        AuditEvent::factory()->create(['action' => 'course.created']);
        AuditEvent::factory()->create(['action' => 'class.proctor_verification.failed', 'after_state' => ['operation' => 'start', 'failure_stage' => 'verification', 'failure_reason' => 'user_not_found']]);

        $response = $this->getJson(route('admin.system-logs.data', ['result' => 'failed']))->assertOk();
        $labels = collect($response->json('data'))->pluck('label');

        $this->assertFalse($labels->contains('Course created'));
        $this->assertTrue($labels->contains('Proctor verification failed'));
    }

    public function test_filtering_by_system_result_only_returns_automatic_events(): void
    {
        AuditEvent::factory()->create(['action' => 'course.created']);
        AuditEvent::factory()->create(['action' => 'class.automatic_start', 'actor_user_id' => null]);

        $response = $this->getJson(route('admin.system-logs.data', ['result' => 'system']))->assertOk();
        $labels = collect($response->json('data'))->pluck('label');

        $this->assertFalse($labels->contains('Course created'));
        $this->assertTrue($labels->contains('Class started automatically'));
    }

    public function test_login_events_appear_in_the_list_without_being_duplicated_into_audit_events(): void
    {
        $student = User::factory()->student()->create();
        LoginEvent::factory()->create(['user_id' => $student->id, 'wellsharp_id' => $student->wellsharp_id, 'outcome' => 'success']);

        $response = $this->getJson(route('admin.system-logs.data'))->assertOk();
        $labels = collect($response->json('data'))->pluck('label');

        $this->assertTrue($labels->contains('Successful login'));
        $this->assertDatabaseMissing('audit_events', ['action' => 'login.success']);
        $this->assertDatabaseCount('login_events', 1);
    }

    public function test_login_outcomes_are_categorized_as_authentication_with_the_right_result(): void
    {
        LoginEvent::factory()->create(['outcome' => 'invalid_credentials', 'user_id' => null]);
        LoginEvent::factory()->create(['outcome' => 'logout']);

        $response = $this->getJson(route('admin.system-logs.data'))->assertOk();
        $rows = collect($response->json('data'));

        $failed = $rows->firstWhere('label', 'Failed login (invalid credentials)');
        $logout = $rows->firstWhere('label', 'User logged out');

        $this->assertSame('failed', $failed['result']);
        $this->assertSame('warning', $failed['severity']);
        $this->assertSame('success', $logout['result']);
        $this->assertSame('info', $logout['severity']);
    }

    public function test_detail_view_redacts_sensitive_nested_values(): void
    {
        $event = AuditEvent::factory()->create([
            'action' => 'user.updated',
            'after_state' => [
                'name' => 'Jane Doe',
                'password' => 'plaintext-secret',
                'profile' => ['token' => 'abc123', 'city' => 'Houston'],
            ],
        ]);

        $response = $this->get(route('admin.system-logs.show', ['audit', $event->public_id]))->assertOk();

        $response->assertDontSee('plaintext-secret');
        $response->assertDontSee('abc123');
        $response->assertSee('[REDACTED]');
        $response->assertSee('Houston');
        $response->assertSee('Jane Doe');
    }

    public function test_detail_view_handles_a_missing_subject_gracefully(): void
    {
        $event = AuditEvent::factory()->create([
            'action' => 'class.updated',
            'subject_type' => TrainingClass::class,
            'subject_id' => '999999',
        ]);

        $this->get(route('admin.system-logs.show', ['audit', $event->public_id]))
            ->assertOk()
            ->assertSee('no longer available');
    }

    public function test_detail_view_handles_an_unknown_legacy_action_gracefully(): void
    {
        $event = AuditEvent::factory()->create(['action' => 'legacy.thing.happened']);

        $this->get(route('admin.system-logs.show', ['audit', $event->public_id]))
            ->assertOk()
            ->assertSee('Legacy Thing Happened');
    }

    public function test_missing_audit_record_returns_not_found(): void
    {
        $this->get(route('admin.system-logs.show', ['audit', 'does-not-exist']))->assertNotFound();
    }

    public function test_list_surfaces_the_reason_for_a_failed_proctor_verification(): void
    {
        AuditEvent::factory()->create([
            'action' => 'class.proctor_verification.failed',
            'after_state' => ['operation' => 'start', 'failure_stage' => 'verification', 'failure_reason' => 'user_not_found'],
            'reason' => "The entered Proctor's ID does not match any credential",
        ]);

        $response = $this->getJson(route('admin.system-logs.data'))->assertOk();
        $row = collect($response->json('data'))->firstWhere('label', 'Proctor verification failed');

        $this->assertNotNull($row);
        $this->assertSame("The entered Proctor's ID does not match any credential", $row['reason']);
    }

    public function test_proctor_id_activity_filter_and_search_cover_all_related_events(): void
    {
        $class = TrainingClass::factory()->create(['class_number' => 'PID-AUDIT-001']);
        foreach ([
            'class.proctor_verification.succeeded',
            'class.proctor_verification.failed',
            'class.control_attempt.failed',
            'class.manual_start',
            'class.manual_end',
        ] as $action) {
            AuditEvent::factory()->create([
                'action' => $action,
                'subject_type' => TrainingClass::class,
                'subject_id' => (string) $class->id,
                'after_state' => [
                    'proctor_id_activity' => true,
                    'entered_proctor_id' => 'PR-TRACE-123',
                    'operation' => str_contains($action, 'end') ? 'end' : 'start',
                    'verification_status' => str_contains($action, 'failed') ? 'failed' : 'success',
                    'control_status' => $action === 'class.control_attempt.failed' ? 'failed' : 'success',
                    'class_number' => $class->class_number,
                ],
            ]);
        }
        AuditEvent::factory()->create([
            'action' => 'class.manual_start',
            'subject_type' => TrainingClass::class,
            'subject_id' => (string) $class->id,
            'after_state' => ['operation' => 'start'],
        ]);
        AuditEvent::factory()->create(['action' => 'course.created']);

        $filtered = $this->getJson(route('admin.system-logs.data', ['category' => 'proctor_id_activity']))->assertOk();
        $this->assertCount(5, $filtered->json('data'));

        $byPhrase = $this->getJson(route('admin.system-logs.data', ['search' => 'proctor id']))->assertOk();
        $this->assertCount(5, $byPhrase->json('data'));

        $byManualStart = $this->getJson(route('admin.system-logs.data', ['search' => 'manual start']))->assertOk();
        $labels = collect($byManualStart->json('data'))->pluck('label');
        $this->assertTrue($labels->contains('Class started manually'));

        $byEnteredId = $this->getJson(route('admin.system-logs.data', ['search' => 'PR-TRACE-123']))->assertOk();
        $this->assertCount(5, $byEnteredId->json('data'));
    }

    public function test_proctor_id_activity_rows_include_friendly_context(): void
    {
        $proctor = User::factory()->proctor()->create();
        AuditEvent::factory()->create([
            'action' => 'class.control_attempt.failed',
            'after_state' => [
                'proctor_id_activity' => true,
                'entered_proctor_id' => 'PR-CONTROL-FAIL',
                'operation' => 'start',
                'verification_status' => 'success',
                'control_status' => 'failed',
                'failure_reason' => 'class_already_active',
                'verified_proctor_user_id' => $proctor->id,
                'verified_proctor_wellsharp_id' => $proctor->wellsharp_id,
                'verified_proctor_display_name' => $proctor->display_name,
                'class_number' => 'PID-CONTROL-FAIL',
            ],
        ]);

        $response = $this->getJson(route('admin.system-logs.data'))->assertOk();
        $row = collect($response->json('data'))->firstWhere('label', 'Class control attempt failed');

        $this->assertSame('Verified / Control Failed', $row['status_label']);
        $this->assertSame('PR-CONTROL-FAIL', $row['proctor_activity']['entered_proctor_id']);
        $this->assertSame($proctor->display_name, $row['proctor_activity']['verified_proctor_display_name']);
        $this->assertSame('class_already_active', $row['proctor_activity']['failure_reason']);
    }

    public function test_detail_view_shows_a_friendly_verification_card_for_a_failed_attempt(): void
    {
        $event = AuditEvent::factory()->create([
            'action' => 'class.proctor_verification.failed',
            'after_state' => ['operation' => 'start', 'failure_stage' => 'verification', 'failure_reason' => 'proctor_inactive'],
        ]);

        $this->get(route('admin.system-logs.show', ['audit', $event->public_id]))
            ->assertOk()
            ->assertSee('Class control attempt')
            ->assertSee('Start Class', false)
            ->assertSee('The Proctor is not active');
    }

    public function test_detail_view_shows_a_friendly_verification_card_for_a_succeeded_attempt(): void
    {
        $proctor = User::factory()->proctor()->create();
        $event = AuditEvent::factory()->create([
            'action' => 'class.proctor_verification.succeeded',
            'after_state' => [
                'operation' => 'end',
                'verified_proctor_user_id' => $proctor->id,
                'verified_proctor_wellsharp_id' => $proctor->wellsharp_id,
            ],
        ]);

        $response = $this->get(route('admin.system-logs.show', ['audit', $event->public_id]))->assertOk();

        $response->assertSee('Succeeded');
        $response->assertSee($proctor->display_name);
        $response->assertSee($proctor->wellsharp_id);
    }

    public function test_detail_view_shows_dedicated_proctor_id_activity_section(): void
    {
        $event = AuditEvent::factory()->create([
            'action' => 'class.proctor_verification.succeeded',
            'after_state' => [
                'proctor_id_activity' => true,
                'entered_proctor_id' => 'PR-DETAIL-123',
                'operation' => 'standalone_verify',
                'verification_status' => 'success',
                'control_status' => 'not_applicable',
                'verified_proctor_display_name' => 'Pat Proctor',
                'verified_proctor_user_id' => 123,
                'verified_proctor_wellsharp_id' => 'WS-PROCTOR',
            ],
        ]);

        $this->get(route('admin.system-logs.show', ['audit', $event->public_id]))
            ->assertOk()
            ->assertSee('Proctor ID Activity')
            ->assertSee('PR-DETAIL-123')
            ->assertSee('Standalone Verify')
            ->assertSee('Pat Proctor')
            ->assertSee('WS-PROCTOR');
    }

    public function test_control_attempt_failed_shows_a_friendly_card_with_failure_stage_and_reason(): void
    {
        $event = AuditEvent::factory()->create([
            'action' => 'class.control_attempt.failed',
            'after_state' => ['operation' => 'start', 'failure_stage' => 'class_state', 'failure_reason' => 'class_already_active'],
        ]);

        $this->get(route('admin.system-logs.show', ['audit', $event->public_id]))
            ->assertOk()
            ->assertSee('Class control attempt')
            ->assertSee('Class State', false)
            ->assertSee('Class is already active');
    }
}
