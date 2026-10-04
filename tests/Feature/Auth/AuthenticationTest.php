<?php

namespace Tests\Feature\Auth;

use App\Enums\ExamScheduleStatus;
use App\Enums\ExamStartMode;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_username_login_is_normalized_and_works_for_every_role(): void
    {
        foreach ([
            'admin' => 'admin.dashboard',
            'proctor' => 'proctor.dashboard',
            'instructor' => 'instructor.dashboard',
            'student' => 'student.dashboard',
        ] as $role => $dashboard) {
            $user = User::factory()->withRole($role)->create(['username' => substr($role.'login', 0, 8)]);
            if ($role === 'student') {
                $this->assignSchedule($user, now()->subDay(), now()->addDay());
            }

            $this->post(route('login.store'), [
                'wellsharp_id' => '  '.strtoupper($user->username).'  ',
                'password' => 'test-password-123',
            ])->assertRedirect(route($dashboard));

            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_wrong_username_or_password_is_rejected(): void
    {
        User::factory()->admin()->create(['username' => 'loginusr']);

        $this->from(route('login'))->post(route('login.store'), [
            'wellsharp_id' => 'loginusr',
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('wellsharp_id');

        $this->assertGuest();
    }

    public function test_legacy_ambiguous_identifier_is_rejected(): void
    {
        User::factory()->admin()->create(['wellsharp_id' => 'SHAREDID']);
        User::factory()->student()->create(['username' => 'sharedid']);

        $this->post(route('login.store'), [
            'wellsharp_id' => 'sharedid',
            'password' => 'test-password-123',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('wellsharp_id');

        $this->assertGuest();
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
        $this->assignSchedule($user, now()->subDay(), now()->addDay());

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

    public function test_login_rate_limit_uses_the_normalized_submitted_identifier(): void
    {
        User::factory()->admin()->create(['username' => 'ratelimt']);
        foreach (['RATELIMT', ' ratelimt ', 'RateLimt', 'ratelimt', 'RATELIMT'] as $identifier) {
            $this->post(route('login.store'), ['wellsharp_id' => $identifier, 'password' => 'wrong-password']);
        }

        $this->post(route('login.store'), ['wellsharp_id' => 'ratelimt', 'password' => 'wrong-password'])->assertStatus(429);
    }

    public function test_student_with_exam_active_now_can_log_in(): void
    {
        $student = User::factory()->student()->create();
        $this->assignSchedule($student, now()->subDay(), now()->addDay());

        $this->post(route('login.store'), ['wellsharp_id' => $student->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_student_with_future_exam_only_is_blocked_with_nearest_start_time(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $student = User::factory()->student()->create();
        $this->assignSchedule($student, now()->addDays(5), now()->addDays(6));
        $nearest = $this->assignSchedule($student, now()->addDays(2), now()->addDays(3));

        $this->from(route('login'))->post(route('login.store'), [
            'wellsharp_id' => $student->wellsharp_id,
            'password' => 'test-password-123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['wellsharp_id' => 'Your next exam is scheduled for '.$nearest->start_date->startOfDay()->format('F j, Y g:i A T').'. Please log in at the exam time.']);

        $this->assertGuest();
    }

    public function test_student_login_before_exact_start_time_is_blocked(): void
    {
        Carbon::setTestNow('2026-10-04 09:29:00');
        $student = User::factory()->student()->create();
        $schedule = $this->assignSchedule($student, now(), now(), ExamScheduleStatus::Scheduled, '09:30', '11:00');

        $this->from(route('login'))->post(route('login.store'), [
            'wellsharp_id' => $student->wellsharp_id,
            'password' => 'test-password-123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['wellsharp_id' => 'Your next exam is scheduled for '.$this->scheduleStartsAt($schedule)->format('F j, Y g:i A T').'. Please log in at the exam time.']);

        $this->assertGuest();
    }

    public function test_student_login_at_exact_start_and_inside_exam_window_is_allowed(): void
    {
        Carbon::setTestNow('2026-10-04 09:30:00');
        $atStart = User::factory()->student()->create();
        $this->assignSchedule($atStart, now(), now(), ExamScheduleStatus::Scheduled, '09:30', '11:00');

        $this->post(route('login.store'), ['wellsharp_id' => $atStart->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($atStart);
        auth()->logout();

        Carbon::setTestNow('2026-10-04 10:15:00');
        $inside = User::factory()->student()->create();
        $this->assignSchedule($inside, now(), now(), ExamScheduleStatus::Scheduled, '09:30', '11:00');

        $this->post(route('login.store'), ['wellsharp_id' => $inside->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($inside);
    }

    public function test_student_login_after_exact_end_time_is_blocked(): void
    {
        Carbon::setTestNow('2026-10-04 11:01:00');
        $student = User::factory()->student()->create();
        $schedule = $this->assignSchedule($student, now(), now(), ExamScheduleStatus::Scheduled, '09:30', '11:00');

        $this->from(route('login'))->post(route('login.store'), [
            'wellsharp_id' => $student->wellsharp_id,
            'password' => 'test-password-123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['wellsharp_id' => 'Your exam ended on '.$this->scheduleEndsAt($schedule)->format('F j, Y g:i A T').'.']);

        $this->assertGuest();
    }

    public function test_future_and_expired_messages_show_exact_schedule_times(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $futureStudent = User::factory()->student()->create();
        $future = $this->assignSchedule($futureStudent, now()->addDay(), now()->addDay(), ExamScheduleStatus::Scheduled, '14:45', '16:15');

        $this->post(route('login.store'), [
            'wellsharp_id' => $futureStudent->wellsharp_id,
            'password' => 'test-password-123',
        ])->assertSessionHasErrors(['wellsharp_id' => 'Your next exam is scheduled for '.$this->scheduleStartsAt($future)->format('F j, Y g:i A T').'. Please log in at the exam time.']);

        $expiredStudent = User::factory()->student()->create();
        $expired = $this->assignSchedule($expiredStudent, now()->subDay(), now()->subDay(), ExamScheduleStatus::Scheduled, '08:00', '10:30');

        $this->post(route('login.store'), [
            'wellsharp_id' => $expiredStudent->wellsharp_id,
            'password' => 'test-password-123',
        ])->assertSessionHasErrors(['wellsharp_id' => 'Your exam ended on '.$this->scheduleEndsAt($expired)->format('F j, Y g:i A T').'.']);
    }

    public function test_legacy_schedule_without_time_uses_whole_day_window(): void
    {
        Carbon::setTestNow('2026-10-04 23:30:00');
        $student = User::factory()->student()->create();
        $this->assignSchedule($student, now(), now());

        $this->post(route('login.store'), ['wellsharp_id' => $student->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_student_with_expired_exam_only_is_blocked_with_latest_end_time(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $student = User::factory()->student()->create();
        $this->assignSchedule($student, now()->subDays(10), now()->subDays(9), ExamScheduleStatus::Completed);
        $latest = $this->assignSchedule($student, now()->subDays(3), now()->subDay(), ExamScheduleStatus::Completed);

        $this->from(route('login'))->post(route('login.store'), [
            'wellsharp_id' => $student->wellsharp_id,
            'password' => 'test-password-123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['wellsharp_id' => 'Your exam ended on '.$latest->end_date->endOfDay()->format('F j, Y g:i A T').'.']);

        $this->assertGuest();
    }

    public function test_student_with_no_exam_assignment_is_blocked(): void
    {
        $student = User::factory()->student()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'wellsharp_id' => $student->wellsharp_id,
            'password' => 'test-password-123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['wellsharp_id' => 'You do not have any scheduled exams at this time.']);

        $this->assertGuest();
    }

    public function test_student_with_past_and_future_exams_gets_future_message(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $student = User::factory()->student()->create();
        $this->assignSchedule($student, now()->subDays(5), now()->subDays(4), ExamScheduleStatus::Completed);
        $future = $this->assignSchedule($student, now()->addDay(), now()->addDays(2));

        $this->post(route('login.store'), [
            'wellsharp_id' => $student->wellsharp_id,
            'password' => 'test-password-123',
        ])->assertSessionHasErrors(['wellsharp_id' => 'Your next exam is scheduled for '.$future->start_date->startOfDay()->format('F j, Y g:i A T').'. Please log in at the exam time.']);

        $this->assertGuest();
    }

    public function test_student_with_active_and_future_exam_can_log_in(): void
    {
        $student = User::factory()->student()->create();
        $this->assignSchedule($student, now()->addDays(3), now()->addDays(4));
        $this->assignSchedule($student, now()->subDay(), now()->addDay());

        $this->post(route('login.store'), ['wellsharp_id' => $student->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_manual_schedule_before_configured_start_blocks_login_until_manually_started(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $blocked = User::factory()->student()->create();
        $this->assignSchedule($blocked, now()->addDays(3), now()->addDays(4), ExamScheduleStatus::Scheduled, '09:00', '17:00', [
            'start_mode' => ExamStartMode::Manual,
        ]);

        $this->post(route('login.store'), ['wellsharp_id' => $blocked->wellsharp_id, 'password' => 'test-password-123'])
            ->assertSessionHasErrors('wellsharp_id');
        $this->assertGuest();

        $started = User::factory()->student()->create();
        $this->assignSchedule($started, now()->addDays(3), now()->addDays(4), ExamScheduleStatus::Scheduled, '09:00', '17:00', [
            'start_mode' => ExamStartMode::Manual,
            'override_started_at' => now(),
        ]);

        $this->post(route('login.store'), ['wellsharp_id' => $started->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($started);
    }

    public function test_automatic_schedule_before_configured_start_allows_login_after_manual_override_start(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $blocked = User::factory()->student()->create();
        $this->assignSchedule($blocked, now()->addDays(3), now()->addDays(4), ExamScheduleStatus::Scheduled, '09:00', '17:00');

        $this->post(route('login.store'), ['wellsharp_id' => $blocked->wellsharp_id, 'password' => 'test-password-123'])
            ->assertSessionHasErrors('wellsharp_id');
        $this->assertGuest();

        $started = User::factory()->student()->create();
        $this->assignSchedule($started, now()->addDays(3), now()->addDays(4), ExamScheduleStatus::Scheduled, '09:00', '17:00', [
            'override_started_at' => now(),
        ]);

        $this->post(route('login.store'), ['wellsharp_id' => $started->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($started);
    }

    public function test_manually_ended_schedule_blocks_login_immediately(): void
    {
        Carbon::setTestNow('2026-10-04 10:00:00');
        $student = User::factory()->student()->create();
        $this->assignSchedule($student, now(), now()->addDay(), ExamScheduleStatus::Scheduled, '09:00', '17:00', [
            'start_mode' => ExamStartMode::Manual,
            'override_started_at' => now()->subHour(),
            'override_ended_at' => now()->subMinute(),
        ]);

        $this->post(route('login.store'), ['wellsharp_id' => $student->wellsharp_id, 'password' => 'test-password-123'])
            ->assertSessionHasErrors('wellsharp_id');

        $this->assertGuest();
    }

    public function test_non_student_roles_keep_existing_login_behavior_without_exam_assignment(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login.store'), ['wellsharp_id' => $admin->wellsharp_id, 'password' => 'test-password-123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_blocked_student_login_leaves_no_authenticated_session(): void
    {
        $student = User::factory()->student()->create();
        $this->assignSchedule($student, now()->addDay(), now()->addDays(2));

        $this->post(route('login.store'), ['wellsharp_id' => $student->wellsharp_id, 'password' => 'test-password-123'])
            ->assertSessionHasErrors('wellsharp_id');

        $this->assertGuest();
        $this->assertFalse(auth()->check());
    }

    private function assignSchedule(
        User $student,
        Carbon $startsAt,
        Carbon $endsAt,
        ExamScheduleStatus $status = ExamScheduleStatus::Scheduled,
        ?string $startTime = null,
        ?string $endTime = null,
        array $attributes = [],
    ): ExamSchedule {
        $group = Group::factory()->create();
        GroupMembership::factory()->create(['group_id' => $group->id, 'student_user_id' => $student->id]);

        return ExamSchedule::factory()->create(array_merge([
            'group_id' => $group->id,
            'start_date' => $startsAt->toDateString(),
            'start_time' => $startTime,
            'end_date' => $endsAt->toDateString(),
            'end_time' => $endTime,
            'status' => $status,
            'start_mode' => ExamStartMode::Automatic,
        ], $attributes));
    }

    private function scheduleStartsAt(ExamSchedule $schedule): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $schedule->start_date->format('Y-m-d').' '.$this->clock($schedule->start_time, '00:00:00'), config('app.timezone'));
    }

    private function scheduleEndsAt(ExamSchedule $schedule): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $schedule->end_date->format('Y-m-d').' '.$this->clock($schedule->end_time, '23:59:59'), config('app.timezone'));
    }

    private function clock(?string $time, string $fallback): string
    {
        $clock = $time ? substr($time, 0, 8) : $fallback;

        return strlen($clock) === 5 ? $clock.':00' : $clock;
    }
}
