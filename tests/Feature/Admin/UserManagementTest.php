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
            'email' => 'proctor@example.test', 'password' => 'Secure12', 'password_confirmation' => 'Secure12',
            'birthday' => '1985-04-12', 'phone' => '+20 100 000 0000', 'address' => '1 Main Street',
            'country' => 'Egypt', 'state' => 'Cairo', 'city' => 'Cairo', 'postal_code' => '11511',
            'company' => 'WellSharp Training', 'position' => 'Senior Proctor', 'employee_id' => 'PRO-001',
            'role_id' => Role::where('key', Role::PROCTOR)->value('id'),
        ]);
        $response->assertRedirect();
        $created = User::where('wellsharp_id', 'PROCTOR-001')->firstOrFail();
        $this->assertNotSame('Secure12', $created->getRawOriginal('password'));
        $this->assertTrue(Hash::check('Secure12', $created->getRawOriginal('password')));
        $this->assertNotEmpty($created->public_id);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5,8}$/', $created->examControlCredential->control_id);
        $this->assertSame('Cairo', $created->profile->state);
        $this->assertSame('Senior Proctor', $created->profile->position);
        $this->assertNull($created->profile->company_contact);
        $this->assertDatabaseHas('role_assignments', ['user_id' => $created->id, 'role_id' => Role::where('key', Role::PROCTOR)->value('id')]);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.created']);
        $audit = AuditEvent::where('action', 'user.created')->latest('id')->firstOrFail();
        $this->assertSame($response->headers->get('X-Correlation-ID'), $audit->correlation_id);
        $this->assertArrayNotHasKey('password', $audit->after_state);
        $this->assertStringNotContainsString('Secure12', json_encode($audit->after_state));
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
            'password' => 'Sec12', 'password_confirmation' => 'Sec12',
            'role_id' => Role::where('key', Role::STUDENT)->value('id'), 'current_role_id' => Role::where('key', Role::ADMIN)->value('id'), 'session_version' => 99,
        ])->assertSessionHasErrors('email');

        $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'SECURITY-001', 'first_name' => 'Secure', 'last_name' => 'User', 'email' => 'secure@example.test',
            'password' => 'Sec12', 'password_confirmation' => 'Sec12',
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
            'password' => 'Secure12',
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
            'password' => 'NewP1', 'password_confirmation' => 'NewP1',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame($oldVersion + 1, $user->session_version);
        $this->assertTrue(Hash::check('NewP1', $user->getRawOriginal('password')));
        $this->post(route('logout'));
        $this->post(route('login.store'), ['wellsharp_id' => $user->wellsharp_id, 'password' => 'test-password-123'])->assertRedirect(route('login'));
        $this->post(route('login.store'), ['wellsharp_id' => $user->wellsharp_id, 'password' => 'NewP1'])->assertRedirect(route('student.dashboard'));
    }

    public function test_student_profile_keeps_student_only_data_and_staff_state_is_not_student_data(): void
    {
        $response = $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'STUDENT-PROFILE-001', 'first_name' => 'Student', 'last_name' => 'Profile',
            'birthday' => '1995-02-03', 'company_contact' => 'Training manager', 'state' => 'Should be cleared',
            'password' => 'Stu12', 'password_confirmation' => 'Stu12',
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
            'password' => 'Stu12', 'password_confirmation' => 'Stu12',
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

    public function test_creating_instructor_admin_or_student_does_not_generate_a_proctors_id(): void
    {
        foreach ([Role::INSTRUCTOR, Role::ADMIN, Role::STUDENT] as $roleKey) {
            $wellsharpId = strtoupper($roleKey).'-NOID-001';
            $password = $roleKey === Role::STUDENT ? 'Nocr1' : 'Secure12';
            $this->post(route('admin.users.store'), [
                'wellsharp_id' => $wellsharpId, 'first_name' => 'No', 'last_name' => 'Credential',
                'password' => $password, 'password_confirmation' => $password,
                'role_id' => Role::where('key', $roleKey)->value('id'),
            ])->assertRedirect();

            $created = User::where('wellsharp_id', $wellsharpId)->firstOrFail();
            $this->assertNull($created->examControlCredential);
        }
    }

    public function test_role_change_between_instructor_and_proctor_generates_and_revokes_the_proctors_id_without_duplicates(): void
    {
        $user = User::factory()->student()->create();
        $proctorRoleId = Role::where('key', Role::PROCTOR)->value('id');
        $instructorRoleId = Role::where('key', Role::INSTRUCTOR)->value('id');

        $this->patch(route('admin.users.role', $user), ['role_id' => $instructorRoleId])->assertRedirect();
        $this->assertNull($user->fresh()->examControlCredential);

        $this->patch(route('admin.users.role', $user), ['role_id' => $proctorRoleId])->assertRedirect();
        $firstProctorId = $user->fresh()->examControlCredential?->control_id;
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5,8}$/', $firstProctorId);
        $this->assertSame(1, $this->app['db']->table('exam_control_credentials')->where('user_id', $user->id)->count());

        $this->patch(route('admin.users.role', $user), ['role_id' => $instructorRoleId])->assertRedirect();
        $this->assertNull($user->fresh()->examControlCredential);
        $this->assertDatabaseMissing('exam_control_credentials', ['user_id' => $user->id]);

        $this->patch(route('admin.users.role', $user), ['role_id' => $proctorRoleId])->assertRedirect();
        $secondProctorId = $user->fresh()->examControlCredential?->control_id;
        $this->assertNotNull($secondProctorId);
        $this->assertSame(1, $this->app['db']->table('exam_control_credentials')->where('user_id', $user->id)->count());
    }

    public function test_admin_can_disable_user_and_audit_it(): void
    {
        $user = User::factory()->student()->create();
        $this->patch(route('admin.users.disable', $user))->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'disabled']);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.disabled']);
    }

    public function test_user_table_uses_alpine_action_for_active_users_and_keeps_archived_users_visible(): void
    {
        $active = User::factory()->student()->create();
        $archived = User::factory()->instructor()->create(['status' => UserStatus::Archived, 'archived_at' => now()]);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('disableUser(user)', false)
            ->assertSee('toggleStatus(user)', false)
            ->assertSee('user-status-toggle', false)
            ->assertSee('archiveUser(user)', false)
            ->assertSee('archive_url', false);

        $this->patchJson(route('admin.users.status', $active), ['status' => 'disabled'])
            ->assertOk()
            ->assertJsonPath('status', 'disabled');
        $this->patchJson(route('admin.users.status', $active), ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('status', 'active');
        $this->assertDatabaseHas('audit_events', ['action' => 'user.status_updated']);

        $this->patchJson(route('admin.users.disable', $active))
            ->assertOk()
            ->assertJsonPath('status', 'disabled');

        $this->assertDatabaseHas('users', ['id' => $active->id, 'status' => 'disabled']);
        $this->assertNotNull($active->fresh()->archived_at);
        $this->assertDatabaseHas('users', ['id' => $archived->id, 'status' => 'archived']);

        $anotherActive = User::factory()->student()->create();
        $this->patchJson(route('admin.users.archive', $anotherActive))
            ->assertOk()
            ->assertJsonPath('status', 'archived');
        $this->assertDatabaseHas('users', ['id' => $anotherActive->id, 'status' => 'archived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.archived']);
    }

    public function test_non_admin_is_denied_user_management(): void
    {
        $this->actingAs(User::factory()->student()->create())->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_creating_any_role_stores_a_recoverable_password_for_admin_management(): void
    {
        $studentResponse = $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'REVEAL-STUDENT-001', 'first_name' => 'Reveal', 'last_name' => 'Student',
            'password' => 'Rvl12', 'password_confirmation' => 'Rvl12',
            'role_id' => Role::where('key', Role::STUDENT)->value('id'),
        ]);
        $studentResponse->assertRedirect();
        $student = User::where('wellsharp_id', 'REVEAL-STUDENT-001')->firstOrFail();

        $this->postJson(route('admin.users.reveal-password', $student))
            ->assertOk()
            ->assertJson(['password' => 'Rvl12']);
        $this->assertDatabaseHas('audit_events', ['action' => 'student.password_viewed']);

        $proctorResponse = $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'REVEAL-PROCTOR-001', 'first_name' => 'Reveal', 'last_name' => 'Proctor',
            'password' => 'Secure12', 'password_confirmation' => 'Secure12',
            'role_id' => Role::where('key', Role::PROCTOR)->value('id'),
        ]);
        $proctorResponse->assertRedirect();
        $proctor = User::where('wellsharp_id', 'REVEAL-PROCTOR-001')->firstOrFail();
        $this->assertNotNull($proctor->password_ciphertext);
        $this->postJson(route('admin.users.reveal-password', $proctor))
            ->assertOk()
            ->assertJson(['password' => 'Secure12']);
    }

    public function test_changing_a_students_password_updates_the_recoverable_copy(): void
    {
        $student = User::factory()->student()->create();
        $student->setPasswordAndCiphertext('original-password-123', Role::STUDENT);
        $student->save();

        $this->put(route('admin.users.update', $student), [
            'first_name' => $student->profile->first_name, 'last_name' => $student->profile->last_name,
            'password' => 'Repl1', 'password_confirmation' => 'Repl1',
        ])->assertRedirect();

        $this->postJson(route('admin.users.reveal-password', $student))
            ->assertOk()
            ->assertJson(['password' => 'Repl1']);
    }

    public function test_changing_role_away_from_student_preserves_the_recoverable_password(): void
    {
        $student = User::factory()->student()->create();
        $student->setPasswordAndCiphertext('will-be-cleared', Role::STUDENT);
        $student->save();

        $this->patch(route('admin.users.role', $student), ['role_id' => Role::where('key', Role::PROCTOR)->value('id')])->assertRedirect();

        $this->assertNotNull($student->fresh()->password_ciphertext);
        $this->postJson(route('admin.users.reveal-password', $student))
            ->assertOk()
            ->assertJson(['password' => 'will-be-cleared']);
    }

    public function test_student_password_is_never_exposed_outside_the_reveal_endpoint(): void
    {
        $student = User::factory()->student()->create();
        $student->setPasswordAndCiphertext('should-not-leak', Role::STUDENT);
        $student->save();

        $this->get(route('admin.users.show', $student))->assertOk()->assertDontSee('should-not-leak');
        $this->get(route('admin.users.index'))->assertOk()->assertDontSee('should-not-leak');
        $this->getJson(route('admin.users.data'))->assertOk()->assertDontSee('should-not-leak');
    }

    public function test_legacy_student_account_without_a_recoverable_password_returns_not_found(): void
    {
        // UserFactory sets a ciphertext for every Student by default
        // (matching CreateUserAction), so the "legacy, no ciphertext" state
        // this test targets is built explicitly rather than relying on a gap.
        $student = User::factory()->student()->create();
        $student->forceFill(['password_ciphertext' => null])->save();

        $this->assertNull($student->fresh()->password_ciphertext);
        $this->postJson(route('admin.users.reveal-password', $student))->assertNotFound();
    }
}
