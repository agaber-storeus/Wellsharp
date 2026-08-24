<?php

namespace Tests\Feature\Classes;

use App\Enums\ClassStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassEnrollmentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create()->fresh();
        $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
    }

    public function test_admin_can_create_class_and_audit_it(): void
    {
        $course = Course::factory()->create(['code' => 'COURSE-001']);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();

        $response = $this->post(route('admin.classes.store'), ['class_number' => 'CLASS-001', 'course_id' => $course->id, 'proctor_id' => $proctor->id, 'instructor_id' => $instructor->id, 'starts_at' => now()->addDay()->format('Y-m-d H:i'), 'notes' => 'Initial class']);
        $response->assertRedirect();

        $this->assertDatabaseHas('classes', ['class_number' => 'CLASS-001', 'course_id' => $course->id, 'proctor_id' => $proctor->id, 'instructor_id' => $instructor->id, 'status' => 'planned']);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.created']);
    }

    public function test_class_creation_requires_proctor_and_instructor(): void
    {
        $course = Course::factory()->create(['code' => 'COURSE-002']);

        $this->post(route('admin.classes.store'), ['class_number' => 'CLASS-002', 'course_id' => $course->id, 'starts_at' => now()->addDay()->format('Y-m-d H:i')])
            ->assertSessionHasErrors(['proctor_id', 'instructor_id']);
        $this->assertDatabaseMissing('classes', ['class_number' => 'CLASS-002']);
    }

    public function test_class_creation_rejects_cross_role_and_inactive_staff_assignment(): void
    {
        $course = Course::factory()->create(['code' => 'COURSE-003']);
        $instructorAsProctor = User::factory()->instructor()->create();
        $studentAsInstructor = User::factory()->student()->create();
        $disabledProctor = User::factory()->proctor()->disabled()->create();
        $activeInstructor = User::factory()->instructor()->create();

        $this->post(route('admin.classes.store'), [
            'class_number' => 'CLASS-003', 'course_id' => $course->id,
            'proctor_id' => $instructorAsProctor->id, 'instructor_id' => $studentAsInstructor->id,
        ])->assertSessionHasErrors(['proctor_id', 'instructor_id']);

        $this->post(route('admin.classes.store'), [
            'class_number' => 'CLASS-003', 'course_id' => $course->id,
            'proctor_id' => $disabledProctor->id, 'instructor_id' => $activeInstructor->id,
        ])->assertSessionHasErrors(['proctor_id']);

        $this->post(route('admin.classes.store'), [
            'class_number' => 'CLASS-003', 'course_id' => $course->id,
            'proctor_id' => 999999, 'instructor_id' => $activeInstructor->id,
        ])->assertSessionHasErrors(['proctor_id']);

        $this->assertDatabaseMissing('classes', ['class_number' => 'CLASS-003']);
    }

    public function test_admin_class_create_form_renders(): void
    {
        $this->get(route('admin.classes.create'))
            ->assertOk()
            ->assertSee('Create class')
            ->assertSee('Class number');
    }

    public function test_completed_class_cannot_be_cancelled_through_direct_request(): void
    {
        $trainingClass = TrainingClass::factory()->create(['status' => ClassStatus::Completed]);

        $this->patch(route('admin.classes.cancel', $trainingClass))->assertSessionHasErrors('class');

        $this->assertDatabaseHas('classes', ['id' => $trainingClass->id, 'status' => 'completed']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'class.cancelled']);
    }

    public function test_class_validation_rejects_missing_or_invalid_course_and_equal_schedule(): void
    {
        $starts = now()->addDay()->format('Y-m-d H:i');
        $this->post(route('admin.classes.store'), ['class_number' => 'CLASS-INVALID', 'course_id' => 999999, 'starts_at' => $starts, 'ends_at' => $starts])->assertSessionHasErrors(['course_id', 'ends_at']);
        $this->assertDatabaseMissing('classes', ['class_number' => 'CLASS-INVALID']);
    }

    public function test_admin_can_enroll_student_and_duplicate_enrollment_is_rejected(): void
    {
        $trainingClass = TrainingClass::factory()->create();
        $student = User::factory()->student()->create(['wellsharp_id' => 'STUDENT-CLASS-001']);

        $this->post(route('admin.classes.enrollments.store', $trainingClass), ['student_user_id' => $student->id])->assertRedirect();
        $this->post(route('admin.classes.enrollments.store', $trainingClass), ['student_user_id' => $student->id])->assertSessionHasErrors('student_user_id');

        $this->assertDatabaseHas('enrollments', ['class_id' => $trainingClass->id, 'student_user_id' => $student->id, 'status' => 'enrolled']);
        $this->assertDatabaseHas('audit_events', ['action' => 'enrollment.created']);
    }

    public function test_class_page_requires_and_displays_staff_assignment(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $trainingClass = TrainingClass::factory()->create(['proctor_id' => $proctor->id, 'instructor_id' => $instructor->id]);

        $this->get(route('admin.classes.show', $trainingClass))
            ->assertOk()
            ->assertSee($proctor->display_name)
            ->assertSee($instructor->display_name)
            ->assertDontSee('Any active Proctor may start or end this Class directly');
    }

    public function test_proctor_and_instructor_dashboards_are_scoped_to_assigned_classes(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $assignedClass = TrainingClass::factory()->create(['class_number' => 'ASSIGNED-001', 'proctor_id' => $proctor->id, 'instructor_id' => $instructor->id]);
        $otherClass = TrainingClass::factory()->create(['class_number' => 'OTHER-001']);

        $proctor = $proctor->fresh();
        $instructor = $instructor->fresh();
        $this->assertTrue(\Gate::forUser($proctor)->allows('view', $assignedClass));
        $this->assertFalse(\Gate::forUser($proctor)->allows('view', $otherClass));
        $this->assertTrue(\Gate::forUser($instructor)->allows('view', $assignedClass));
        $this->assertFalse(\Gate::forUser($instructor)->allows('view', $otherClass));
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])->get(route('proctor.dashboard'))->assertOk()->assertSee('ASSIGNED-001')->assertDontSee('OTHER-001');
        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version])->get(route('instructor.dashboard'))->assertOk()->assertSee('ASSIGNED-001')->assertDontSee('OTHER-001');
    }

    public function test_student_dashboard_shows_only_own_enrollment(): void
    {
        $ownClass = TrainingClass::factory()->create(['class_number' => 'OWN-001']);
        $otherClass = TrainingClass::factory()->create(['class_number' => 'NOT-OWN-001']);
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $this->post(route('admin.classes.enrollments.store', $ownClass), ['student_user_id' => $student->id]);
        $this->post(route('admin.classes.enrollments.store', $otherClass), ['student_user_id' => $otherStudent->id]);

        $student = $student->fresh();
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])->get(route('student.dashboard'))->assertOk()->assertSee('Review Your Contact Information')->assertDontSee('OWN-001')->assertDontSee('NOT-OWN-001');
    }

    public function test_withdrawal_preserves_history_and_removes_active_student_visibility(): void
    {
        $trainingClass = TrainingClass::factory()->create(['class_number' => 'WITHDRAW-001']);
        $student = User::factory()->student()->create();
        $this->post(route('admin.classes.enrollments.store', $trainingClass), ['student_user_id' => $student->id]);
        $enrollment = Enrollment::firstOrFail();

        $this->patch(route('admin.classes.enrollments.withdraw', [$trainingClass, $enrollment]))->assertRedirect();
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'withdrawn']);
        $this->assertDatabaseHas('audit_events', ['action' => 'enrollment.withdrawn']);
        $student = $student->fresh();
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])->get(route('student.dashboard'))->assertOk()->assertDontSee('WITHDRAW-001');
    }

    public function test_non_admin_cannot_use_admin_class_routes(): void
    {
        $student = User::factory()->student()->create()->fresh();
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])->get(route('admin.classes.index'))->assertForbidden();
        $trainingClass = TrainingClass::factory()->create();
        $this->get(route('admin.classes.show', $trainingClass))->assertForbidden();
        $this->get('/admin/classes/01J00000000000000000000000')->assertNotFound();
    }
}
