<?php

namespace Tests\Feature\Operational;

use App\Enums\StaffAssignmentRole;
use App\Models\ClassStaffAssignment;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_classes_over_time_embeds_the_full_database_backed_dataset_for_alpine(): void
    {
        $this->seedRoles();
        $proctor = User::factory()->proctor()->create();
        $instructorUser = User::factory()->instructor()->create();

        $foundation = CourseLevel::where('slug', 'foundation')->firstOrFail();
        $advanced = CourseLevel::where('slug', 'advanced')->firstOrFail();

        $foundationCourse = Course::factory()->create(['name' => 'Foundation Drilling', 'course_level_id' => $foundation->id]);
        $advancedCourse = Course::factory()->create(['name' => 'Advanced Drilling', 'course_level_id' => $advanced->id]);

        $classWithInstructor = TrainingClass::factory()->create(['course_id' => $foundationCourse->id, 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeeks(2)->addDay()]);
        ClassStaffAssignment::create(['class_id' => $classWithInstructor->id, 'user_id' => $instructorUser->id, 'assignment_role' => StaffAssignmentRole::Instructor, 'status' => 'active', 'assigned_at' => now()]);

        $classWithProctor = TrainingClass::factory()->create(['course_id' => $advancedCourse->id, 'starts_at' => now()->subWeeks(6), 'ends_at' => now()->subWeeks(6)->addDay()]);
        ClassStaffAssignment::create(['class_id' => $classWithProctor->id, 'user_id' => $proctor->id, 'assignment_role' => StaffAssignmentRole::Proctor, 'status' => 'active', 'assigned_at' => now()]);

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            // Filters in the URL only bootstrap Alpine's initial state now — the server always embeds
            // the full authorized dataset so client-side filtering/sorting/pagination has everything.
            ->get(route('proctor.analytics', ['role' => 'Proctors', 'course_level_id' => $advanced->id]))
            ->assertOk()
            ->assertSee('wellsharpClassesAnalytics', false)
            ->assertSee('By Week')
            ->assertSee('By Month')
            ->assertSee('By Quarter')
            ->assertSee('Bar Chart')
            ->assertSee('Line Chart')
            ->assertSee('classes-line-svg', false)
            ->assertSee('Foundation')
            ->assertSee('Advanced');

        [$rows, $initialFilters] = $this->decodeAlpineArgs($response, 'wellsharpClassesAnalytics');

        $foundationRow = collect($rows)->firstWhere('course_level_id', $foundation->id);
        $advancedRow = collect($rows)->firstWhere('course_level_id', $advanced->id);
        $this->assertNotNull($foundationRow, 'The foundation-level class should be present in the embedded dataset.');
        $this->assertNotNull($advancedRow, 'The advanced-level class should be present in the embedded dataset.');
        $this->assertSame(['instructor'], $foundationRow['staff_roles']);
        $this->assertSame(['proctor'], $advancedRow['staff_roles']);

        // Query-string filters are bootstrapped into Alpine's initial state, not applied server-side.
        $this->assertSame('Proctors', $initialFilters['role']);
        $this->assertSame((string) $advanced->id, $initialFilters['course_level_id']);
    }

    public function test_assessment_comparison_embeds_the_full_database_backed_dataset_for_alpine(): void
    {
        $fixture = $this->fixture();

        $response = $this->actingAs($fixture['proctor'])->withSession(['auth.session_version' => $fixture['proctor']->session_version])
            ->get(route('proctor.analytics.results', ['date_range' => 'All Time']))
            ->assertOk()
            ->assertSee('wellsharpAssessmentResults', false)
            ->assertSee('Assessment Comparison')
            ->assertSee('Course Results')
            ->assertSee('browse-sort-button', false);

        // Roles/course/date-range filters and pagination/sort are handled entirely client-side now;
        // the export link still hits the server (real filtering + xlsx) with the live filter state.
        [$rows, , $exportUrl] = $this->decodeAlpineArgs($response, 'wellsharpAssessmentResults');
        $this->assertSame($fixture['exportRoute'], $exportUrl);

        $attempt = collect($rows)->firstWhere('exam_name', 'Analytics Exam');
        $this->assertNotNull($attempt, 'The scored attempt should be present in the embedded dataset.');
        $this->assertSame($fixture['student']->id, $attempt['student_user_id']);
        $this->assertTrue($attempt['passed']);
        $this->assertEquals(88.0, $attempt['score']);
    }

    public function test_export_still_applies_server_side_filters_and_produces_a_real_spreadsheet(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['proctor'])->withSession(['auth.session_version' => $fixture['proctor']->session_version]);

        $response = $this->get(route('proctor.analytics.results.export', ['date_range' => 'All Time']));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Instructors/proctors never take exams in this system, so the export is honestly empty for that role.
        $this->get(route('proctor.analytics.results.export', ['date_range' => 'All Time', 'role' => 'Instructors']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_totals_dataset_includes_every_assessment_for_a_shared_trainee(): void
    {
        $this->seedRoles();
        $proctor = User::factory()->proctor()->create();
        $student = User::factory()->student()->create();
        $provider = TrainingProvider::factory()->create(['name' => 'Scale Provider']);

        for ($i = 1; $i <= 3; $i++) {
            $course = Course::factory()->create(['name' => "Scale Course {$i}"]);
            $class = TrainingClass::factory()->create(['course_id' => $course->id, 'training_provider_id' => $provider->id, 'class_number' => "SCALE-00{$i}"]);
            $group = Group::create(['name' => "Scale Group {$i}", 'status' => 'active']);
            $exam = Exam::create(['course_id' => $course->id, 'name' => "Scale Exam {$i}", 'passing_score' => 70, 'retake_score' => 60, 'question_order_mode' => 'static', 'status' => 'published']);
            $schedule = ExamSchedule::create(['exam_id' => $exam->id, 'group_id' => $group->id, 'training_class_id' => $class->id, 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'duration_minutes' => 60, 'status' => 'completed']);
            ExamAttempt::create(['exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $student->id, 'attempt_number' => 1, 'status' => 'submitted', 'started_at' => now()->subDay(), 'submitted_at' => now()->subDay(), 'score' => 100, 'passed' => true]);
        }

        // The totals row's summing (rather than global-dedupe) math now lives client-side in
        // wellsharpAssessmentResults.totals (assessment-results.blade.php); this verifies the
        // three exam attempts for the same shared trainee all land in the embedded dataset,
        // which is what that client-side computation depends on being correct.
        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.analytics.results', ['date_range' => 'All Time']))
            ->assertOk();

        [$rows] = $this->decodeAlpineArgs($response, 'wellsharpAssessmentResults');
        $examNames = collect($rows)->pluck('exam_name')->unique()->sort()->values()->all();
        $this->assertSame(['Scale Exam 1', 'Scale Exam 2', 'Scale Exam 3'], $examNames);
        $this->assertCount(3, $rows);
        $this->assertTrue(collect($rows)->every(fn (array $attempt): bool => $attempt['student_user_id'] === $student->id));
    }

    /** @return array{proctor: User, student: User, exportRoute: string} */
    private function fixture(): array
    {
        $this->seedRoles();
        $proctor = User::factory()->proctor()->create();
        $student = User::factory()->student()->create();
        $provider = TrainingProvider::factory()->create(['name' => 'Analytics Provider']);
        $course = Course::factory()->create(['name' => 'Analytics Subject']);
        $class = TrainingClass::factory()->create(['course_id' => $course->id, 'training_provider_id' => $provider->id, 'class_number' => 'ANALYTICS-001']);
        Enrollment::create(['class_id' => $class->id, 'student_user_id' => $student->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
        $group = Group::create(['name' => 'Analytics Group', 'status' => 'active']);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Analytics Exam', 'passing_score' => 70, 'retake_score' => 60, 'question_order_mode' => 'static', 'status' => 'published']);
        $schedule = ExamSchedule::create(['exam_id' => $exam->id, 'group_id' => $group->id, 'training_class_id' => $class->id, 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'duration_minutes' => 60, 'status' => 'completed']);
        ExamAttempt::create(['exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $student->id, 'attempt_number' => 1, 'status' => 'submitted', 'started_at' => now()->subDay(), 'submitted_at' => now()->subDay(), 'score' => 88, 'passed' => true]);

        return ['proctor' => $proctor, 'student' => $student, 'exportRoute' => route('proctor.analytics.results.export')];
    }

    /**
     * Decodes the arguments passed to an Alpine `x-data="fn(...)"` call. Blade's @js()
     * renders each argument differently depending on its shape (Illuminate\Support\Js::from()):
     * non-trivial arrays/objects become `JSON.parse('...')` with quotes unicode-escaped for
     * XSS safety, plain strings become a bare single-quoted JS string, and an empty array
     * becomes a bare `[]`. The payload can't be asserted against as literal text, so it has
     * to be extracted and decoded per its actual rendered form instead.
     *
     * @return array<int, mixed>
     */
    private function decodeAlpineArgs(TestResponse $response, string $functionName): array
    {
        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertMatchesRegularExpression('/x-data="'.preg_quote($functionName, '/').'\((.*?)\)">/s', $html);
        preg_match('/x-data="'.preg_quote($functionName, '/').'\((.*?)\)">/s', $html, $outer);

        preg_match_all("/JSON\\.parse\\('(.*?)'\\)|'([^']*)'|(\\[\\]|\\{\\})/s", $outer[1], $matches, PREG_SET_ORDER);

        return array_map(function (array $match): mixed {
            if (($match[1] ?? '') !== '') {
                return json_decode(str_replace('\\u0022', '"', $match[1]), true);
            }
            if (($match[2] ?? '') !== '') {
                return str_replace('\\/', '/', $match[2]);
            }

            return json_decode($match[3] ?? $match[0], true);
        }, $matches);
    }
}
