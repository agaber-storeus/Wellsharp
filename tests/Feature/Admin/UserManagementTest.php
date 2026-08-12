<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
    }

    public function test_admin_can_list_and_create_users(): void
    {
        User::factory()->student()->create(['wellsharp_id' => 'STUDENT-001']);
        $this->get(route('admin.users.index'))->assertOk()->assertSee('STUDENT-001');

        $response = $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'PROCTOR-001', 'first_name' => 'Proctor', 'last_name' => 'One',
            'email' => 'proctor@example.test', 'password' => 'a-secure-test-password', 'password_confirmation' => 'a-secure-test-password',
            'birthday' => '1985-04-12', 'phone' => '+20 100 000 0000', 'address' => '1 Main Street',
            'country' => 'Egypt', 'state' => 'Cairo', 'city' => 'Cairo', 'postal_code' => '11511',
            'company' => 'WellSharp Training', 'position' => 'Senior Proctor', 'employee_id' => 'PRO-001',
            'role_id' => Role::where('key', Role::PROCTOR)->value('id'),
        ]);
        $response->assertRedirect();
        $created = User::where('wellsharp_id', 'PROCTOR-001')->firstOrFail();
        $this->assertNotSame('a-secure-test-password', $created->getRawOriginal('password'));
        $this->assertTrue(Hash::check('a-secure-test-password', $created->getRawOriginal('password')));
        $this->assertNotEmpty($created->public_id);
        $this->assertMatchesRegularExpression('/^PR-[A-Z0-9]{10}$/', $created->examControlCredential->control_id);
        $this->assertSame('Cairo', $created->profile->state);
        $this->assertSame('Senior Proctor', $created->profile->position);
        $this->assertNull($created->profile->company_contact);
        $this->assertDatabaseHas('role_assignments', ['user_id' => $created->id, 'role_id' => Role::where('key', Role::PROCTOR)->value('id')]);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.created']);
        $audit = AuditEvent::where('action', 'user.created')->latest('id')->firstOrFail();
        $this->assertSame($response->headers->get('X-Correlation-ID'), $audit->correlation_id);
        $this->assertArrayNotHasKey('password', $audit->after_state);
        $this->assertStringNotContainsString('a-secure-test-password', json_encode($audit->after_state));
    }

    public function test_admin_user_create_form_renders_its_partial(): void
    {
        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('First name')
            ->assertSee('State / Province')
            ->assertSee('Company contact')
            ->assertSee('WellSharp ID')
            ->assertDontSee("@include('admin.users._form')");
    }

    public function test_user_creation_ignores_security_fields_and_rejects_invalid_input(): void
    {
        $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'SECURITY-001', 'first_name' => 'Secure', 'last_name' => 'User', 'email' => 'not-an-email',
            'password' => 'a-secure-test-password', 'password_confirmation' => 'a-secure-test-password',
            'role_id' => Role::where('key', Role::STUDENT)->value('id'), 'current_role_id' => Role::where('key', Role::ADMIN)->value('id'), 'session_version' => 99,
        ])->assertSessionHasErrors('email');

        $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'SECURITY-001', 'first_name' => 'Secure', 'last_name' => 'User', 'email' => 'secure@example.test',
            'password' => 'a-secure-test-password', 'password_confirmation' => 'a-secure-test-password',
            'role_id' => Role::where('key', Role::STUDENT)->value('id'), 'current_role_id' => Role::where('key', Role::ADMIN)->value('id'), 'session_version' => 99,
        ])->assertRedirect();

        $created = User::where('wellsharp_id', 'SECURITY-001')->firstOrFail();
        $this->assertSame(Role::STUDENT, $created->currentRole->key);
        $this->assertSame(1, $created->fresh()->session_version);
    }

    public function test_user_model_does_not_mass_assign_security_fields(): void
    {
        $user = User::create([
            'wellsharp_id' => 'MODEL-SECURITY-001',
            'email' => 'model-security@example.test',
            'password' => 'a-secure-test-password',
            'status' => UserStatus::Disabled,
            'current_role_id' => Role::where('key', Role::ADMIN)->value('id'),
            'session_version' => 99,
        ])->fresh();

        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->current_role_id);
        $this->assertSame(1, $user->session_version);
    }

    public function test_password_change_revokes_version_and_replaces_credential(): void
    {
        $user = User::factory()->student()->create()->fresh();
        $oldVersion = $user->session_version;

        $this->put(route('admin.users.update', $user), [
            'first_name' => $user->profile->first_name, 'last_name' => $user->profile->last_name,
            'password' => 'a-new-secure-password', 'password_confirmation' => 'a-new-secure-password',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame($oldVersion + 1, $user->session_version);
        $this->assertTrue(Hash::check('a-new-secure-password', $user->getRawOriginal('password')));
        $this->post(route('logout'));
        $this->post(route('login.store'), ['wellsharp_id' => $user->wellsharp_id, 'password' => 'test-password-123'])->assertRedirect(route('login'));
        $this->post(route('login.store'), ['wellsharp_id' => $user->wellsharp_id, 'password' => 'a-new-secure-password'])->assertRedirect(route('student.dashboard'));
    }

    public function test_student_profile_keeps_student_only_data_and_staff_state_is_not_student_data(): void
    {
        $response = $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'STUDENT-PROFILE-001', 'first_name' => 'Student', 'last_name' => 'Profile',
            'birthday' => '1995-02-03', 'company_contact' => 'Training manager', 'state' => 'Should be cleared',
            'password' => 'a-secure-test-password', 'password_confirmation' => 'a-secure-test-password',
            'role_id' => Role::where('key', Role::STUDENT)->value('id'),
        ]);

        $response->assertRedirect();
        $student = User::where('wellsharp_id', 'STUDENT-PROFILE-001')->firstOrFail()->load('profile');
        $this->assertSame('Training manager', $student->profile->company_contact);
        $this->assertNull($student->profile->state);
        $this->get(route('admin.users.show', $student))->assertOk()->assertSee('Company contact')->assertDontSee('State / Province');
    }

    public function test_duplicate_wellsharp_id_is_rejected(): void
    {
        User::factory()->student()->create(['wellsharp_id' => 'DUP-001']);

        $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'dup-001', 'first_name' => 'Duplicate', 'last_name' => 'User',
            'password' => 'a-secure-test-password', 'password_confirmation' => 'a-secure-test-password',
            'role_id' => Role::where('key', Role::STUDENT)->value('id'),
        ])->assertSessionHasErrors('wellsharp_id');
    }

    public function test_role_change_writes_history_audit_and_revokes_sessions(): void
    {
        $user = User::factory()->student()->create();
        $user->profile()->update(['company_contact' => 'Student Contact', 'state' => 'Should be cleared', 'age' => 30, 'gender' => 'Male']);
        $oldVersion = $user->fresh()->session_version;

        $this->patch(route('admin.users.role', $user), ['role_id' => Role::where('key', Role::PROCTOR)->value('id')])->assertRedirect();

        $user->refresh();
        $this->assertSame(Role::PROCTOR, $user->currentRole->key);
        $this->assertSame($oldVersion + 1, $user->session_version);
        $this->assertNull($user->profile->fresh()->company_contact);
        $this->assertNull($user->profile->fresh()->state);
        $this->assertNull($user->profile->fresh()->age);
        $this->assertNull($user->profile->fresh()->gender);
        $this->assertDatabaseHas('role_assignments', ['user_id' => $user->id, 'ended_at' => null]);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.role_changed']);
    }

    public function test_admin_can_disable_user_and_audit_it(): void
    {
        $user = User::factory()->student()->create();
        $this->patch(route('admin.users.disable', $user))->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'disabled']);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.disabled']);
    }

    public function test_non_admin_is_denied_user_management(): void
    {
        $this->actingAs(User::factory()->student()->create())->get(route('admin.users.index'))->assertForbidden();
    }
}
