<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLogsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_can_access_system_logs(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.system-logs.index'))
            ->assertOk()
            ->assertSee('System Logs');
    }

    public function test_non_admin_roles_cannot_access_system_logs(): void
    {
        foreach (['proctor', 'instructor', 'student'] as $role) {
            $user = User::factory()->withRole($role)->create()->fresh();
            $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version]);

            $this->get(route('admin.system-logs.index'))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_from_system_logs(): void
    {
        $this->get(route('admin.system-logs.index'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_system_logs_under_settings_navigation(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.system-logs.index'), false);
    }

    public function test_json_data_endpoint_requires_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->getJson(route('admin.system-logs.data'))->assertOk();

        foreach (['proctor', 'instructor', 'student'] as $role) {
            $user = User::factory()->withRole($role)->create()->fresh();
            $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version]);

            $this->getJson(route('admin.system-logs.data'))->assertForbidden();
        }
    }

    public function test_non_admin_cannot_open_a_log_detail_page_directly(): void
    {
        $admin = User::factory()->admin()->create();
        $event = AuditEvent::factory()->create(['actor_user_id' => $admin->id]);

        $student = User::factory()->student()->create();
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);

        $this->get(route('admin.system-logs.show', ['audit', $event->public_id]))->assertForbidden();
    }
}
