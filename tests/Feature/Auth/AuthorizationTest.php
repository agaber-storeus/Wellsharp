<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_only_admin_can_access_admin_routes(): void
    {
        foreach (['proctor', 'instructor', 'student'] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create())->get(route('admin.dashboard'))->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk();
    }

    public function test_guest_is_redirected_from_representative_admin_routes(): void
    {
        foreach ([route('admin.users.index'), route('admin.providers.index'), route('admin.courses.index'), route('admin.classes.index')] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_non_admin_roles_are_denied_across_representative_admin_routes(): void
    {
        foreach (['proctor', 'instructor', 'student'] as $role) {
            $user = User::factory()->withRole($role)->create()->fresh();
            $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version]);
            foreach ([route('admin.users.index'), route('admin.providers.index'), route('admin.courses.index'), route('admin.classes.index')] as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }

    public function test_role_change_invalidates_existing_session_version(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version]);
        $user->forceFill(['session_version' => $user->session_version + 1])->save();

        $this->get(route('home'))->assertRedirect(route('login'))->assertSessionHasErrors('wellsharp_id');
        $this->assertGuest();
    }

    public function test_disabled_account_cannot_continue_using_an_existing_session(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version]);
        $user->forceFill(['status' => 'disabled', 'archived_at' => now(), 'session_version' => $user->session_version + 1])->save();

        $this->get(route('home'))->assertRedirect(route('login'))->assertSessionHasErrors('wellsharp_id');
        $this->assertGuest();
    }
}
