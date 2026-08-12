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

        $response = $this->post(route('admin.classes.store'), ['class_number' => 'CLASS-001', 'course_id' => $course->id, 'starts_at' => now()->addDay()->format('Y-m-d H:i'), 'notes' => 'Initial class']);
        $response->assertRedirect();

        $this->assertDatabaseHas('classes', ['class_number' => 'CLASS-001', 'course_id' => $course->id, 'status' => 'planned']);
        $this->assertDatabaseHas('audit_events', ['action' => 'class.created']);
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

    public function test_class_page_does_not_require_staff_assignment(): void
    {
        $trainingClass = TrainingClass::factory()->create();
        $this->get(route('admin.classes.show', $trainingClass))
            ->assertOk()
            ->assertSee('Any active Proctor or Instructor may control this Class')
            ->assertDontSee('Class staff');
    }

    public function test_proctor_and_instructor_dashboards_are_not_assignment_scoped(): void
    {
        $assignedClass = TrainingClass::factory()->create(['class_number' => 'ASSIGNED-001']);
        $otherClass = TrainingClass::factory()->create(['class_number' => 'OTHER-001']);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();

        $proctor = $proctor->fresh();
        $instructor = $instructor->fresh();
        $this->assertTrue(\Gate::forUser($proctor)->allows('view', $assignedClass));
        $this->assertTrue(\Gate::forUser($proctor)->allows('view', $otherClass));
        $this->assertTrue(\Gate::forUser($instructor)->allows('view', $assignedClass));
        $this->assertTrue(\Gate::forUser($instructor)->allows('view', $otherClass));
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])->get(route('proctor.dashboard'))->assertOk()->assertSee('ASSIGNED-001')->assertSee('OTHER-001');
        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version])->get(route('instructor.dashboard'))->assertOk()->assertSee('ASSIGNED-001')->assertSee('OTHER-001');
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
