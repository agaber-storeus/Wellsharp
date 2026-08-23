<?php

namespace Tests\Feature\Auth;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_home_redirects_each_role_to_its_own_dashboard(): void
    {
        foreach ([
            'admin' => 'admin.dashboard',
            'proctor' => 'proctor.dashboard',
            'instructor' => 'instructor.dashboard',
            'student' => 'student.dashboard',
        ] as $role => $dashboard) {
            $user = User::factory()->withRole($role)->create()->fresh();

            $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version])
                ->get(route('home'))
                ->assertRedirect(route($dashboard));
        }
    }

    public function test_login_redirects_each_role_to_its_own_dashboard(): void
    {
        foreach ([
            'admin' => 'admin.dashboard',
            'proctor' => 'proctor.dashboard',
            'instructor' => 'instructor.dashboard',
            'student' => 'student.dashboard',
        ] as $role => $dashboard) {
            $user = User::factory()->withRole($role)->create(['wellsharp_id' => strtoupper($role).'-LOGIN']);

            $this->post(route('login.store'), ['wellsharp_id' => $user->wellsharp_id, 'password' => 'test-password-123'])
                ->assertRedirect(route($dashboard));

            auth()->logout();
        }
    }

    public function test_each_role_renders_only_its_own_dashboard_shell(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Administration console')
            ->assertSee('sidebarCollapsed:', false)
            ->assertSee('admin-sidebar-collapse', false)
            ->assertSee('wellsharp.admin.sidebarCollapsed', false)
            ->assertSee('x-on:click="open = ! open"', false)
            ->assertDontSee('Operational workspace');

        $proctor = User::factory()->proctor()->create();
        $proctorClass = TrainingClass::factory()->create(['class_number' => 'PROCTOR-ONLY', 'proctor_id' => $proctor->id]);
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.dashboard'))
            ->assertOk()
            ->assertSee('Ongoing Classes')
            ->assertSee('PROCTOR-ONLY')
            ->assertSee('data-class-modal="details"', false)
            ->assertSee('data-class-state="notstarted"', false)
            ->assertSee('wellsharpClassModalData', false)
            ->assertDontSee('Administration console');

        $instructor = User::factory()->instructor()->create();
        $instructorClass = TrainingClass::factory()->create(['class_number' => 'INSTRUCTOR-ONLY', 'instructor_id' => $instructor->id]);
        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version])
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee('Ongoing Classes')
            ->assertSee('INSTRUCTOR-ONLY')
            ->assertSee('operational-map-filter', false)
            ->assertDontSee('Administration console');

        $student = User::factory()->student()->create();
        $studentClass = TrainingClass::factory()->create(['class_number' => 'STUDENT-ONLY']);
        Enrollment::factory()->create(['class_id' => $studentClass->id, 'student_user_id' => $student->id]);
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Review Your Contact Information')
            ->assertSee('Continue')
            ->assertDontSee('student-schedule-panel')
            ->assertDontSee('Select the class you want to continue')
            ->assertDontSee('Current Class Enrollment')
            ->assertDontSee('STUDENT-ONLY')
            ->assertDontSee('Administration console');
    }

    public function test_operational_dashboards_are_scoped_to_assigned_classes_while_students_remain_enrollment_scoped(): void
    {
        $proctor = User::factory()->proctor()->create();
        $assigned = TrainingClass::factory()->create(['class_number' => 'ASSIGNED-CLASS', 'proctor_id' => $proctor->id]);
        $unassigned = TrainingClass::factory()->create(['class_number' => 'UNASSIGNED-CLASS']);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.dashboard'))
            ->assertSee('ASSIGNED-CLASS')
            ->assertDontSee('UNASSIGNED-CLASS');

        $student = User::factory()->student()->create();
        $own = TrainingClass::factory()->create(['class_number' => 'OWN-CLASS']);
        $other = TrainingClass::factory()->create(['class_number' => 'OTHER-CLASS']);
        Enrollment::factory()->create(['class_id' => $own->id, 'student_user_id' => $student->id]);
        Enrollment::factory()->create(['class_id' => $other->id]);

        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])
            ->get(route('student.dashboard'))
            ->assertSee('Review Your Contact Information')
            ->assertDontSee('OWN-CLASS')
            ->assertDontSee('OTHER-CLASS');
    }

    public function test_operational_dashboard_exposes_scoped_class_map_points(): void
    {
        $proctor = User::factory()->proctor()->create();
        $provider = TrainingProvider::factory()->create(['latitude' => 30.0444, 'longitude' => 31.2357, 'address' => 'Cairo Training Center']);
        $class = TrainingClass::factory()->create([
            'class_number' => 'MAP-CLASS',
            'training_provider_id' => $provider->id,
            'proctor_id' => $proctor->id,
            'status' => 'active',
            // Relative to "now" (not fixed calendar dates) so this class is
            // still genuinely ongoing whenever the test runs - the operational
            // dashboard now auto-transitions an active Class whose ends_at has
            // already passed (SyncDueClassStatuses middleware), so a fixed
            // past date would flip this fixture to "completed" mid-test.
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay()->setTime(10, 56),
        ]);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.dashboard'))
            ->assertOk()
            ->assertSee('homeLeafletMap')
            ->assertSee('"group":"ongoing"', false)
            ->assertSee('"durationDays":3', false)
            ->assertSee('"lat":30.0444', false)
            ->assertSee('Cairo Training Center', false);
    }

    public function test_class_dashboard_uses_exam_schedule_dates_and_not_class_end_time(): void
    {
        $proctor = User::factory()->proctor()->create();
        $trainingClass = TrainingClass::factory()->create([
            'class_number' => 'SCHEDULE-DATES',
            'proctor_id' => $proctor->id,
            'status' => 'planned',
            'starts_at' => '2026-08-17 00:00:00',
            'ends_at' => '2026-08-19 10:56:00',
        ]);
        $exam = Exam::create([
            'course_id' => $trainingClass->course_id,
            'name' => 'Schedule Date Exam',
            'status' => 'published',
            'question_order_mode' => 'static',
        ]);
        ExamSchedule::create([
            'exam_id' => $exam->id,
            'group_id' => Group::create(['name' => 'Schedule Date Group', 'status' => 'active'])->id,
            'training_class_id' => $trainingClass->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-19',
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.dashboard'))
            ->assertSee('August 17 - 19, 2026', false)
            ->assertDontSee('August 19, 2026 10:56 AM', false);
    }

    public function test_roles_cannot_cross_access_other_role_groups(): void
    {
        foreach ([
            ['proctor', 'instructor.dashboard'],
            ['instructor', 'proctor.dashboard'],
            ['student', 'proctor.dashboard'],
            ['student', 'admin.dashboard'],
        ] as [$role, $route]) {
            $user = User::factory()->withRole($role)->create()->fresh();

            $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version])
                ->get(route($route))
                ->assertForbidden();
        }
    }

    public function test_operational_sidebar_pages_render_for_each_staff_role(): void
    {
        foreach (['proctor', 'instructor'] as $role) {
            $user = User::factory()->withRole($role)->create()->fresh();
            $session = $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version]);

            $session->get(route($role.'.dashboard'))->assertOk()->assertSee('homeLeafletMap');
            $session->get(route($role.'.classes'))
                ->assertOk()
                ->assertSee('classesLeafletMap')
                ->assertSee('classesTimeFrame')
                ->assertSee('Calendar Schedule')
                ->assertSee('wellsharpCalendar', false)
                ->assertDontSee('Continuous multi-day class', false)
                ->assertDontSee('<div class="panel-title">Past Classes</div>', false);

            foreach (['profile', 'analytics', 'analytics.search', 'analytics.results', 'classes', 'browse', 'browse.results', 'certificate'] as $page) {
                $session->get(route($role.'.'.$page))->assertOk();
            }

            auth()->logout();
        }
    }
}
