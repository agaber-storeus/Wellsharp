<?php

namespace Tests\Feature\Operational;

use App\Actions\Exams\SaveExamScheduleAction;
use App\Enums\ClassStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassDashboardRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function scheduleExamForGroup(Course $course, Group $group, array $overrides = []): ExamSchedule
    {
        $exam = Exam::create(['course_id' => $course->id, 'name' => $overrides['exam_name'] ?? 'Roster Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $admin = User::factory()->admin()->create();

        // Goes through the real unified Exam/Group scheduling action rather than
        // raw Eloquent, so it exercises the same code path production traffic uses.
        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version]);

        return app(SaveExamScheduleAction::class)->execute(null, [
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'duration_minutes' => 60,
            'proctor_id' => $overrides['proctor_id'] ?? User::factory()->proctor()->create()->id,
            'instructor_id' => $overrides['instructor_id'] ?? User::factory()->instructor()->create()->id,
        ]);
    }

    public function test_proctor_sees_students_from_a_group_scheduled_through_the_unified_exam_flow(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create(['name' => 'Roster Course']);
        $group = Group::create(['name' => 'Roster Group', 'status' => 'active']);
        $student = User::factory()->student()->create();
        $student->profile()->update(['first_name' => 'Rosa', 'last_name' => 'Enrolled']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);

        $schedule = $this->scheduleExamForGroup($course, $group, ['proctor_id' => $proctor->id]);

        $this->assertDatabaseHas('enrollments', ['class_id' => $schedule->training_class_id, 'student_user_id' => $student->id, 'status' => 'enrolled']);

        $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'))
            ->assertOk()
            ->assertSee('Rosa Enrolled');
    }

    public function test_instructor_sees_the_same_students_on_the_same_class_dashboard(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create(['name' => 'Instructor Roster Course']);
        $group = Group::create(['name' => 'Instructor Roster Group', 'status' => 'active']);
        $student = User::factory()->student()->create();
        $student->profile()->update(['first_name' => 'Isaac', 'last_name' => 'Trainee']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);

        $this->scheduleExamForGroup($course, $group, ['instructor_id' => $instructor->id]);

        $this->actingAs($instructor)
            ->withSession(['auth.session_version' => $instructor->session_version])
            ->get(route('instructor.classes'))
            ->assertOk()
            ->assertSee('Isaac Trainee');
    }

    public function test_student_from_an_unrelated_group_is_not_shown_on_this_class_dashboard(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create(['name' => 'Isolation Course']);

        $group = Group::create(['name' => 'Isolation Group A', 'status' => 'active']);
        $member = User::factory()->student()->create();
        $member->profile()->update(['first_name' => 'Belongs', 'last_name' => 'Here']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $member->id, 'status' => 'active', 'joined_at' => now()]);

        $otherGroup = Group::create(['name' => 'Isolation Group B', 'status' => 'active']);
        $stranger = User::factory()->student()->create();
        $stranger->profile()->update(['first_name' => 'Unrelated', 'last_name' => 'Stranger']);
        GroupMembership::create(['group_id' => $otherGroup->id, 'student_user_id' => $stranger->id, 'status' => 'active', 'joined_at' => now()]);

        $this->scheduleExamForGroup($course, $group, ['proctor_id' => $proctor->id]);

        $response = $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'))
            ->assertOk();

        $response->assertSee('Belongs Here')->assertDontSee('Unrelated Stranger');
    }

    public function test_multiple_classes_do_not_cross_contaminate_rosters(): void
    {
        $courseA = Course::factory()->create(['name' => 'Cross Course A']);
        $courseB = Course::factory()->create(['name' => 'Cross Course B']);

        $groupA = Group::create(['name' => 'Cross Group A', 'status' => 'active']);
        $studentA = User::factory()->student()->create();
        $studentA->profile()->update(['first_name' => 'Alpha', 'last_name' => 'Student']);
        GroupMembership::create(['group_id' => $groupA->id, 'student_user_id' => $studentA->id, 'status' => 'active', 'joined_at' => now()]);

        $groupB = Group::create(['name' => 'Cross Group B', 'status' => 'active']);
        $studentB = User::factory()->student()->create();
        $studentB->profile()->update(['first_name' => 'Bravo', 'last_name' => 'Student']);
        GroupMembership::create(['group_id' => $groupB->id, 'student_user_id' => $studentB->id, 'status' => 'active', 'joined_at' => now()]);

        $scheduleA = $this->scheduleExamForGroup($courseA, $groupA, ['exam_name' => 'Cross Exam A']);
        $scheduleB = $this->scheduleExamForGroup($courseB, $groupB, ['exam_name' => 'Cross Exam B']);

        $this->assertNotSame($scheduleA->training_class_id, $scheduleB->training_class_id);
        $this->assertDatabaseHas('enrollments', ['class_id' => $scheduleA->training_class_id, 'student_user_id' => $studentA->id]);
        $this->assertDatabaseMissing('enrollments', ['class_id' => $scheduleA->training_class_id, 'student_user_id' => $studentB->id]);
        $this->assertDatabaseHas('enrollments', ['class_id' => $scheduleB->training_class_id, 'student_user_id' => $studentB->id]);
        $this->assertDatabaseMissing('enrollments', ['class_id' => $scheduleB->training_class_id, 'student_user_id' => $studentA->id]);
    }

    public function test_legacy_add_group_schedule_flow_still_populates_the_roster(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $admin = User::factory()->admin()->create();
        $course = Course::factory()->create(['name' => 'Legacy Flow Course']);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Legacy Flow Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $group = Group::create(['name' => 'Legacy Flow Group', 'status' => 'active']);
        $student = User::factory()->student()->create();
        $student->profile()->update(['first_name' => 'Legacy', 'last_name' => 'Attendee']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);

        // Standalone admin.exam-schedules.store — the legacy "Add Group Schedule" screen,
        // as distinct from the newer bundled Exam+Group+Dates creation flow.
        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->post(route('admin.exam-schedules.store'), [
                'exam_id' => $exam->id,
                'group_id' => $group->id,
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addDays(2)->format('Y-m-d'),
                'duration_minutes' => 60,
                'proctor_id' => $proctor->id,
                'instructor_id' => $instructor->id,
            ])->assertRedirect();

        $schedule = ExamSchedule::where('exam_id', $exam->id)->firstOrFail();
        $this->assertDatabaseHas('enrollments', ['class_id' => $schedule->training_class_id, 'student_user_id' => $student->id, 'status' => 'enrolled']);

        $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'))
            ->assertOk()
            ->assertSee('Legacy Attendee');
    }

    public function test_pre_existing_class_created_before_this_fix_can_be_backfilled_via_the_sync_command(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create(['name' => 'Backfill Course']);
        $group = Group::create(['name' => 'Backfill Group', 'status' => 'active']);
        $student = User::factory()->student()->create();
        $student->profile()->update(['first_name' => 'Backfilled', 'last_name' => 'Student']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);

        // Simulate data created before this fix existed: an ExamSchedule/TrainingClass
        // pair with no Enrollment rows, bypassing SaveExamScheduleAction entirely.
        $trainingClass = TrainingClass::factory()->create([
            'course_id' => $course->id,
            'proctor_id' => $proctor->id,
            'status' => ClassStatus::Planned,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Backfill Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        ExamSchedule::create([
            'exam_id' => $exam->id, 'group_id' => $group->id, 'training_class_id' => $trainingClass->id,
            'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString(),
            'duration_minutes' => 60, 'status' => 'scheduled',
        ]);

        $this->assertDatabaseMissing('enrollments', ['class_id' => $trainingClass->id, 'student_user_id' => $student->id]);

        $this->artisan('wellsharp:sync-class-enrollments')->assertSuccessful();

        $this->assertDatabaseHas('enrollments', ['class_id' => $trainingClass->id, 'student_user_id' => $student->id, 'status' => 'enrolled']);

        $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'))
            ->assertOk()
            ->assertSee('Backfilled Student');

        // Idempotent: re-running does not create a duplicate row.
        $this->artisan('wellsharp:sync-class-enrollments')->assertSuccessful();
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_roster_count_matches_the_enrollments_backing_the_dashboard(): void
    {
        $course = Course::factory()->create(['name' => 'Count Course']);
        $group = Group::create(['name' => 'Count Group', 'status' => 'active']);
        $students = User::factory()->count(3)->student()->create();
        foreach ($students as $student) {
            GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);
        }

        $schedule = $this->scheduleExamForGroup($course, $group);

        $this->assertSame(3, Enrollment::where('class_id', $schedule->training_class_id)->count());
        $this->assertSame(3, TrainingClass::withCount('enrollments')->find($schedule->training_class_id)->enrollments_count);
    }

    public function test_unauthorized_role_cannot_view_the_class_dashboard(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->withSession(['auth.session_version' => $student->session_version])
            ->get(route('proctor.classes'))
            ->assertForbidden();
    }
}
