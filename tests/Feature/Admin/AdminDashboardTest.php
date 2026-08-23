<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_load_the_dashboard(): void
    {
        $this->seedRoles();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin dashboard')
            ->assertSee('Class lifecycle')
            ->assertSee('Exam performance')
            ->assertSee('Skills Score overrides')
            ->assertSee('Certificates')
            ->assertSee('Attention required')
            ->assertSee('Recent activity');
    }

    public function test_non_admin_roles_cannot_access_the_dashboard(): void
    {
        $this->seedRoles();

        foreach (['proctor', 'instructor', 'student'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version])
                ->get(route('admin.dashboard'))
                ->assertForbidden();
        }
    }

    public function test_empty_database_renders_without_errors_and_has_no_divide_by_zero(): void
    {
        $this->seedRoles();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('—');

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(0, $data['class_status']['total']);
        $this->assertNull($data['exam_performance']['pass_rate']);
        $this->assertNull($data['exam_performance']['average_effective_score']);
        $this->assertSame([], $data['attention']);
        $this->assertSame([], $data['recent_activity']);
    }

    public function test_class_status_counts_use_the_authoritative_status_field(): void
    {
        $this->seedRoles();
        TrainingClass::factory()->count(2)->create(['status' => 'planned', 'starts_at' => now()->addDays(3)]);
        TrainingClass::factory()->active()->create();
        TrainingClass::factory()->completed()->count(3)->create();
        TrainingClass::factory()->cancelled()->create();

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(7, $data['class_status']['total']);
        $counts = collect($data['class_status']['breakdown'])->keyBy('status');
        $this->assertSame(2, $counts['planned']['count']);
        $this->assertSame(1, $counts['active']['count']);
        $this->assertSame(3, $counts['completed']['count']);
        $this->assertSame(1, $counts['cancelled']['count']);
    }

    public function test_legacy_unassigned_classes_are_counted_and_flagged(): void
    {
        $this->seedRoles();
        TrainingClass::factory()->create();
        TrainingClass::factory()->create(['proctor_id' => null]);
        TrainingClass::factory()->create(['instructor_id' => null]);

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(2, $data['class_status']['legacy_unassigned']);
        $this->assertTrue(collect($data['attention'])->contains(
            fn (array $item): bool => str_contains($item['label'], 'Proctor or Instructor')
        ));
    }

    public function test_enrollment_totals_distinguish_unique_students_from_enrollment_rows(): void
    {
        $this->seedRoles();
        $classOne = TrainingClass::factory()->create();
        $classTwo = TrainingClass::factory()->create();
        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();

        Enrollment::factory()->create(['class_id' => $classOne->id, 'student_user_id' => $studentA->id]);
        Enrollment::factory()->withdrawn()->create(['class_id' => $classOne->id, 'student_user_id' => $studentB->id]);
        Enrollment::factory()->create(['class_id' => $classTwo->id, 'student_user_id' => $studentA->id]);

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(3, $data['enrollment']['total']);
        $this->assertSame(2, $data['enrollment']['unique_students']);
        $this->assertSame(2, $data['enrollment']['by_status']['enrolled']);
        $this->assertSame(1, $data['enrollment']['by_status']['withdrawn']);
    }

    /**
     * The scenario from the spec: pass/fail must use the effective score
     * (Skills Score override when set, otherwise raw Knowledge score), not
     * the raw Knowledge Exam result alone.
     */
    public function test_pass_fail_and_pass_rate_use_the_effective_score(): void
    {
        $this->seedRoles();
        $course = Course::factory()->create();
        $exam = Exam::factory()->published()->create(['course_id' => $course->id, 'passing_score' => 70]);
        $class = TrainingClass::factory()->create(['course_id' => $course->id]);
        $schedule = ExamSchedule::factory()->create(['exam_id' => $exam->id, 'training_class_id' => $class->id]);

        // Student A: Knowledge 90, no Skills Score -> effective 90 -> pass.
        $studentA = User::factory()->student()->create();
        Enrollment::factory()->create(['class_id' => $class->id, 'student_user_id' => $studentA->id]);
        ExamAttempt::factory()->submitted(true, 90)->create(['exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $studentA->id]);

        // Student B: Knowledge 30 (raw fail), Skills Score 75 -> effective 75 -> pass.
        $studentB = User::factory()->student()->create();
        Enrollment::factory()->create(['class_id' => $class->id, 'student_user_id' => $studentB->id, 'skills_score' => 75]);
        ExamAttempt::factory()->submitted(false, 30)->create(['exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $studentB->id]);

        // Student C: Knowledge 90 (raw pass), Skills Score 50 -> effective 50 -> fail.
        $studentC = User::factory()->student()->create();
        Enrollment::factory()->create(['class_id' => $class->id, 'student_user_id' => $studentC->id, 'skills_score' => 50]);
        ExamAttempt::factory()->submitted(true, 90)->create(['exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $studentC->id]);

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(2, $data['exam_performance']['passed']);
        $this->assertSame(1, $data['exam_performance']['failed']);
        $this->assertEqualsWithDelta(66.7, $data['exam_performance']['pass_rate'], 0.1);

        $this->assertSame(2, $data['skills_overrides']['active']);
        $this->assertSame(1, $data['skills_overrides']['fail_to_pass']);
        $this->assertSame(1, $data['skills_overrides']['pass_to_fail']);
        $this->assertSame(0, $data['skills_overrides']['no_change']);
    }

    public function test_certificate_issued_and_revoked_counts_are_correct(): void
    {
        $this->seedRoles();
        Certificate::factory()->count(3)->create(['status' => 'issued']);
        Certificate::factory()->revoked()->create();

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(3, $data['certificates']['issued']);
        $this->assertSame(1, $data['certificates']['revoked']);
    }

    public function test_certificate_expiration_buckets_use_the_raw_expiry_timestamp(): void
    {
        $this->seedRoles();
        Certificate::factory()->create(['status' => 'issued', 'expires_at' => now()->addYear()]);
        Certificate::factory()->create(['status' => 'issued', 'expires_at' => now()->addDays(10)]);
        Certificate::factory()->create(['status' => 'issued', 'expires_at' => now()->subDay()]);
        Certificate::factory()->create(['status' => 'issued', 'expires_at' => null]);

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(4, $data['certificates']['issued']);
        $this->assertSame(3, $data['certificates']['currently_valid']);
        $this->assertSame(1, $data['certificates']['expired']);
        $this->assertSame(1, $data['certificates']['expiring_soon']);
    }

    public function test_staff_counts_and_workload_distinguish_proctor_and_instructor(): void
    {
        $this->seedRoles();
        $proctorOne = User::factory()->proctor()->create();
        $proctorTwo = User::factory()->proctor()->create();
        $instructorOne = User::factory()->instructor()->create();
        User::factory()->proctor()->disabled()->create();

        TrainingClass::factory()->create(['proctor_id' => $proctorOne->id, 'instructor_id' => $instructorOne->id, 'status' => 'active']);
        TrainingClass::factory()->create(['proctor_id' => $proctorOne->id, 'instructor_id' => $instructorOne->id, 'status' => 'planned']);
        TrainingClass::factory()->create(['proctor_id' => $proctorTwo->id, 'instructor_id' => $instructorOne->id, 'status' => 'planned']);

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(2, $data['staff']['active_proctors']);
        $this->assertSame(1, $data['staff']['active_instructors']);

        $proctorWorkload = collect($data['staff']['proctor_workload'])->keyBy('name');
        $this->assertSame(2, $proctorWorkload[$proctorOne->display_name]['count']);
        $this->assertSame(1, $proctorWorkload[$proctorTwo->display_name]['count']);

        $instructorWorkload = collect($data['staff']['instructor_workload'])->keyBy('name');
        $this->assertSame(3, $instructorWorkload[$instructorOne->display_name]['count']);
    }

    public function test_ongoing_and_upcoming_classes_are_scoped_and_ordered_by_start_date(): void
    {
        $this->seedRoles();
        TrainingClass::factory()->active()->create(['class_number' => 'ONGOING-1']);
        TrainingClass::factory()->create(['class_number' => 'SOON-1', 'status' => 'planned', 'starts_at' => now()->addDay()]);
        TrainingClass::factory()->create(['class_number' => 'LATER-1', 'status' => 'planned', 'starts_at' => now()->addDays(10)]);
        TrainingClass::factory()->completed()->create(['class_number' => 'PAST-1']);

        $data = app(AdminDashboardService::class)->build();

        $ongoing = collect($data['upcoming_classes']['ongoing'])->pluck('class_number');
        $upcoming = collect($data['upcoming_classes']['upcoming'])->pluck('class_number');

        $this->assertTrue($ongoing->contains('ONGOING-1'));
        $this->assertFalse($ongoing->contains('PAST-1'));
        $this->assertSame(['SOON-1', 'LATER-1'], $upcoming->all());
    }

    public function test_admin_dashboard_totals_are_not_restricted_to_a_single_staff_members_classes(): void
    {
        $this->seedRoles();
        $proctorOne = User::factory()->proctor()->create();
        $proctorTwo = User::factory()->proctor()->create();
        TrainingClass::factory()->create(['proctor_id' => $proctorOne->id]);
        TrainingClass::factory()->create(['proctor_id' => $proctorTwo->id]);

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(2, $data['class_status']['total']);
    }

    public function test_stuck_in_progress_attempts_past_expiry_are_flagged(): void
    {
        $this->seedRoles();
        $schedule = ExamSchedule::factory()->create();
        ExamAttempt::factory()->create([
            'exam_schedule_id' => $schedule->id,
            'exam_id' => $schedule->exam_id,
            'status' => 'in_progress',
            'expires_at' => now()->subHour(),
        ]);

        $data = app(AdminDashboardService::class)->build();

        $this->assertTrue(collect($data['attention'])->contains(
            fn (array $item): bool => str_contains($item['label'], 'past their expiry')
        ));
    }

    public function test_users_overview_breaks_down_by_role_and_active_status(): void
    {
        $this->seedRoles();
        User::factory()->admin()->create();
        User::factory()->proctor()->count(2)->create();
        User::factory()->instructor()->create();
        User::factory()->student()->count(3)->create();
        User::factory()->proctor()->disabled()->create();
        User::factory()->student()->archived()->create();

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(9, $data['users']['total']);
        $this->assertSame(7, $data['users']['active']);
        $this->assertSame(1, $data['users']['disabled']);
        $this->assertSame(1, $data['users']['archived']);

        $byRole = collect($data['users']['by_role'])->keyBy('key');
        $this->assertSame(1, $byRole['admin']['total']);
        $this->assertSame(3, $byRole['proctor']['total']);
        $this->assertSame(2, $byRole['proctor']['active']);
        $this->assertSame(4, $byRole['student']['total']);
        $this->assertSame(3, $byRole['student']['active']);
    }

    public function test_providers_overview_counts_status_and_top_providers_by_class_count(): void
    {
        $this->seedRoles();
        $providerA = TrainingProvider::factory()->create();
        $providerB = TrainingProvider::factory()->create();
        TrainingProvider::factory()->inactive()->create();
        TrainingProvider::factory()->archived()->create();

        TrainingClass::factory()->count(3)->create(['training_provider_id' => $providerA->id]);
        TrainingClass::factory()->create(['training_provider_id' => $providerB->id]);

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(4, $data['providers']['total']);
        $this->assertSame(2, $data['providers']['active']);
        $this->assertSame(1, $data['providers']['inactive']);
        $this->assertSame(1, $data['providers']['archived']);

        $top = collect($data['providers']['top'])->keyBy('name');
        $this->assertSame(3, $top[$providerA->name]['count']);
        $this->assertSame(1, $top[$providerB->name]['count']);
    }

    public function test_subjects_overview_flags_active_courses_without_an_exam_and_counts_questions(): void
    {
        $this->seedRoles();
        $withExam = Course::factory()->create();
        Exam::factory()->create(['course_id' => $withExam->id]);
        Course::factory()->create();
        Course::factory()->retired()->create();

        Question::factory()->mcq()->create(['course_id' => $withExam->id]);
        Question::factory()->trueFalse()->create(['course_id' => $withExam->id]);
        Question::factory()->input()->inactive()->create(['course_id' => $withExam->id]);

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(2, $data['subjects']['courses_active']);
        $this->assertSame(1, $data['subjects']['courses_retired']);
        $this->assertSame(1, $data['subjects']['courses_without_exam']);
        $this->assertSame(3, $data['subjects']['questions_total']);
        $this->assertSame(2, $data['subjects']['questions_active']);
        $this->assertSame(1, $data['subjects']['questions_mcq']);
        $this->assertSame(1, $data['subjects']['questions_true_false']);
        $this->assertSame(1, $data['subjects']['questions_input']);

        $this->assertTrue(collect($data['attention'])->contains(
            fn (array $item): bool => str_contains($item['label'], 'no exam configured')
        ));
    }

    public function test_groups_overview_counts_active_members_and_exam_schedule_status(): void
    {
        $this->seedRoles();
        $groupA = Group::factory()->create();
        $groupB = Group::factory()->create();
        Group::factory()->archived()->create();

        GroupMembership::factory()->count(3)->create(['group_id' => $groupA->id]);
        GroupMembership::factory()->create(['group_id' => $groupA->id, 'status' => 'removed']);
        GroupMembership::factory()->count(2)->create(['group_id' => $groupB->id]);

        ExamSchedule::factory()->create(['status' => 'scheduled', 'group_id' => $groupA->id]);
        ExamSchedule::factory()->completed()->create(['group_id' => $groupA->id]);
        ExamSchedule::factory()->cancelled()->create(['group_id' => $groupB->id]);

        $data = app(AdminDashboardService::class)->build();

        $this->assertSame(2, $data['groups']['active']);
        $this->assertSame(1, $data['groups']['schedules_scheduled']);
        $this->assertSame(1, $data['groups']['schedules_completed']);
        $this->assertSame(1, $data['groups']['schedules_cancelled']);

        $top = collect($data['groups']['top'])->keyBy('name');
        $this->assertSame(3, $top[$groupA->name]['count']);
        $this->assertSame(2, $top[$groupB->name]['count']);
    }

    /**
     * Regression guard: the dashboard must run a fixed, small set of
     * aggregate queries regardless of table size, not one query (or more)
     * per Class/Enrollment/Attempt row. Both runs seed enough rows to fill
     * every limited list (ongoing/upcoming classes, staff workload top-5)
     * so eager-load queries fire in both cases - only the underlying table
     * size differs between the two runs, isolating true N+1 growth from
     * the one-time "eager load fires only when there are rows" difference.
     */
    public function test_dashboard_query_count_does_not_scale_with_table_size(): void
    {
        $this->seedRoles();
        $this->seedDashboardVolume(classes: 8, enrollmentsPerClass: 2);

        DB::enableQueryLog();
        app(AdminDashboardService::class)->build();
        $baselineQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        $this->seedDashboardVolume(classes: 80, enrollmentsPerClass: 2);

        DB::enableQueryLog();
        app(AdminDashboardService::class)->build();
        $largeTableQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        $this->assertSame($baselineQueries, $largeTableQueries, 'Dashboard query count must not grow as the underlying tables grow.');
    }

    private function seedDashboardVolume(int $classes, int $enrollmentsPerClass): void
    {
        for ($i = 0; $i < $classes; $i++) {
            $class = TrainingClass::factory()->create([
                'status' => $i % 2 === 0 ? 'active' : 'planned',
                'starts_at' => $i % 2 === 0 ? now()->subDay() : now()->addDays($i + 1),
            ]);
            Enrollment::factory()->count($enrollmentsPerClass)->create(['class_id' => $class->id]);
        }
    }
}
