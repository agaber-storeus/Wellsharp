<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_valid_login_regenerates_session_and_redirects_admin(): void
    {
        $user = User::factory()->admin()->create(['wellsharp_id' => 'ADMIN-001']);
        $oldSession = $this->app['session']->getId();

        $response = $this->post(route('login.store'), ['wellsharp_id' => 'admin-001', 'password' => 'test-password-123']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, $this->app['session']->getId());
        $response->assertSessionHas('auth.session_version', $user->session_version);
        $this->assertDatabaseHas('login_events', ['wellsharp_id' => 'ADMIN-001', 'outcome' => 'success']);
    }

    public function test_invalid_credentials_are_rejected_without_disclosing_which_field_failed(): void
    {
        User::factory()->admin()->create(['wellsharp_id' => 'ADMIN-002']);

        $response = $this->from(route('login'))->post(route('login.store'), ['wellsharp_id' => 'ADMIN-002', 'password' => 'wrong-password']);

        $response->assertRedirect(route('login'))->assertSessionHasErrors('wellsharp_id');
        $this->assertGuest();
        $this->assertDatabaseHas('login_events', ['outcome' => 'invalid_credentials']);
    }

    public function test_unknown_id_and_whitespace_input_are_handled_safely(): void
    {
        $this->post(route('login.store'), ['wellsharp_id' => ' UNKNOWN ', 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('wellsharp_id');
        $this->from(route('login'))->post(route('login.store'), ['wellsharp_id' => '   ', 'password' => ''])
            ->assertRedirect(route('login'))->assertSessionHasErrors(['wellsharp_id', 'password']);
        $this->assertDatabaseHas('login_events', ['wellsharp_id' => 'UNKNOWN', 'outcome' => 'invalid_credentials']);
    }

    public function test_non_admin_login_reaches_the_shared_role_landing_page(): void
    {
        $user = User::factory()->student()->create(['wellsharp_id' => 'STUDENT-001']);

        $this->post(route('login.store'), ['wellsharp_id' => $user->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_disabled_users_cannot_log_in(): void
    {
        $user = User::factory()->admin()->create(['status' => 'disabled', 'wellsharp_id' => 'ADMIN-003']);

        $this->post(route('login.store'), ['wellsharp_id' => $user->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('wellsharp_id');

        $this->assertGuest();
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'outcome' => 'inactive']);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version])->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'outcome' => 'logout']);
    }

    public function test_login_rate_limit_is_applied(): void
    {
        User::factory()->admin()->create(['wellsharp_id' => 'ADMIN-004']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['wellsharp_id' => 'ADMIN-004', 'password' => 'wrong-password']);
        }

        $this->post(route('login.store'), ['wellsharp_id' => 'ADMIN-004', 'password' => 'wrong-password'])->assertStatus(429);
    }
}
