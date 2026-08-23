<?php

namespace Tests\Feature\Classes;

use App\Models\Enrollment;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Class ownership/staff-assignment business rule end to end: every
 * Class has exactly one Proctor and one Instructor (classes.proctor_id /
 * instructor_id), a Proctor/Instructor may only see and act on Classes
 * assigned to them, Admin is unrestricted, and reassignment flips access
 * immediately without touching historical data. Creation validation and the
 * Admin Classes create/edit UI are covered in ClassEnrollmentAssignmentTest;
 * this file focuses on relationships, scoping, IDOR protection, and
 * reassignment.
 */
class ClassStaffOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_class_resolves_the_correct_proctor_and_instructor(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $trainingClass = TrainingClass::factory()->create(['proctor_id' => $proctor->id, 'instructor_id' => $instructor->id]);

        $this->assertTrue($trainingClass->proctor->is($proctor));
        $this->assertTrue($trainingClass->instructor->is($instructor));
    }

    public function test_a_proctor_and_an_instructor_can_each_own_multiple_classes(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        TrainingClass::factory()->count(3)->create(['proctor_id' => $proctor->id, 'instructor_id' => $instructor->id]);

        $this->assertSame(3, $proctor->proctoredClasses()->count());
        $this->assertSame(3, $instructor->instructedClasses()->count());
    }

    public function test_proctor_sees_only_their_own_class_on_the_dashboard_and_browse_pages(): void
    {
        $proctorA = User::factory()->proctor()->create();
        $proctorB = User::factory()->proctor()->create();
        $classA = TrainingClass::factory()->create(['class_number' => 'PROCTOR-A-CLASS', 'proctor_id' => $proctorA->id]);
        $classB = TrainingClass::factory()->create(['class_number' => 'PROCTOR-B-CLASS', 'proctor_id' => $proctorB->id]);

        $sessionA = $this->actingAs($proctorA)->withSession(['auth.session_version' => $proctorA->session_version]);
        $sessionA->get(route('proctor.dashboard'))->assertOk()->assertSee('PROCTOR-A-CLASS')->assertDontSee('PROCTOR-B-CLASS');
        $sessionA->get(route('proctor.classes'))->assertOk()->assertSee('PROCTOR-A-CLASS')->assertDontSee('PROCTOR-B-CLASS');
        $sessionA->get(route('proctor.browse.results'))->assertOk()->assertSee('PROCTOR-A-CLASS')->assertDontSee('PROCTOR-B-CLASS');

        $sessionB = $this->actingAs($proctorB)->withSession(['auth.session_version' => $proctorB->session_version]);
        $sessionB->get(route('proctor.dashboard'))->assertOk()->assertSee('PROCTOR-B-CLASS')->assertDontSee('PROCTOR-A-CLASS');
    }

    public function test_instructor_sees_only_their_own_class_on_the_dashboard(): void
    {
        $instructorA = User::factory()->instructor()->create();
        $instructorB = User::factory()->instructor()->create();
        TrainingClass::factory()->create(['class_number' => 'INSTRUCTOR-A-CLASS', 'instructor_id' => $instructorA->id]);
        TrainingClass::factory()->create(['class_number' => 'INSTRUCTOR-B-CLASS', 'instructor_id' => $instructorB->id]);

        $this->actingAs($instructorA)->withSession(['auth.session_version' => $instructorA->session_version])
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee('INSTRUCTOR-A-CLASS')
            ->assertDontSee('INSTRUCTOR-B-CLASS');

        $this->actingAs($instructorB)->withSession(['auth.session_version' => $instructorB->session_version])
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee('INSTRUCTOR-B-CLASS')
            ->assertDontSee('INSTRUCTOR-A-CLASS');
    }

    public function test_admin_sees_every_class_regardless_of_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        TrainingClass::factory()->create(['class_number' => 'ADMIN-VIEW-001']);
        TrainingClass::factory()->create(['class_number' => 'ADMIN-VIEW-002']);

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->get(route('admin.classes.index'))
            ->assertOk()
            ->assertSee('ADMIN-VIEW-001')
            ->assertSee('ADMIN-VIEW-002');
    }

    /**
     * IDOR: Proctor A cannot directly open/control Proctor B's Class merely
     * by guessing/changing the URL, even though route model binding resolves
     * the Class fine (it exists) - the policy must still reject it.
     */
    public function test_proctor_a_cannot_directly_control_proctor_bs_class(): void
    {
        $proctorA = User::factory()->proctor()->create();
        $proctorB = User::factory()->proctor()->create();
        $classB = TrainingClass::factory()->create(['proctor_id' => $proctorB->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

        $this->actingAs($proctorA)->withSession(['auth.session_version' => $proctorA->session_version])
            ->postJson(route('proctor.classes.exam-control', $classB), ['action' => 'start'])
            ->assertForbidden();

        $this->actingAs($proctorA)->withSession(['auth.session_version' => $proctorA->session_version])
            ->postJson(route('proctor.classes.student-passwords', $classB))
            ->assertForbidden();
    }

    public function test_instructor_a_cannot_directly_control_instructor_bs_class(): void
    {
        $instructorA = User::factory()->instructor()->create();
        $instructorB = User::factory()->instructor()->create();
        $classB = TrainingClass::factory()->create(['instructor_id' => $instructorB->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

        $this->actingAs($instructorA)->withSession(['auth.session_version' => $instructorA->session_version])
            ->postJson(route('instructor.classes.exam-control', $classB), ['action' => 'start', 'proctor_id' => 'IRRELEVANT'])
            ->assertForbidden();

        $this->actingAs($instructorA)->withSession(['auth.session_version' => $instructorA->session_version])
            ->postJson(route('instructor.classes.student-passwords', $classB))
            ->assertForbidden();
    }

    public function test_assigned_staff_can_access_their_own_class(): void
    {
        $proctor = User::factory()->proctor()->create();
        $trainingClass = TrainingClass::factory()->create(['proctor_id' => $proctor->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.exam-control', $trainingClass), ['action' => 'start'])
            ->assertOk()
            ->assertJsonPath('class.status', 'active');
    }

    /**
     * The IDOR regression case the business explicitly asked for: a Skills
     * Score update on an Enrollment belonging to a Class assigned to a
     * different Proctor must be rejected, not merely hidden from a list.
     */
    public function test_proctor_cannot_update_skills_score_for_an_enrollment_in_another_proctors_class(): void
    {
        $proctorA = User::factory()->proctor()->create();
        $proctorB = User::factory()->proctor()->create();
        $classB = TrainingClass::factory()->create(['proctor_id' => $proctorB->id]);
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::create([
            'class_id' => $classB->id,
            'student_user_id' => $student->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($proctorA)->withSession(['auth.session_version' => $proctorA->session_version])
            ->postJson(route('proctor.enrollments.skills-score', $enrollment), ['skills_score' => 90])
            ->assertForbidden();

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'skills_score' => null]);
    }

    public function test_admin_can_edit_class_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $originalProctor = User::factory()->proctor()->create();
        $newProctor = User::factory()->proctor()->create();
        $originalInstructor = User::factory()->instructor()->create();
        $trainingClass = TrainingClass::factory()->create(['proctor_id' => $originalProctor->id, 'instructor_id' => $originalInstructor->id]);

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->put(route('admin.classes.update', $trainingClass), [
                'class_number' => $trainingClass->class_number,
                'course_id' => $trainingClass->course_id,
                'proctor_id' => $newProctor->id,
                'instructor_id' => $originalInstructor->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('classes', ['id' => $trainingClass->id, 'proctor_id' => $newProctor->id]);
    }

    /**
     * Reassignment must flip access immediately: the old Proctor loses
     * visibility/control, the new Proctor gains it - while the Class's own
     * data (enrollments, etc.) is left completely untouched.
     */
    public function test_reassigning_the_proctor_removes_old_access_and_grants_new_access(): void
    {
        $admin = User::factory()->admin()->create();
        $oldProctor = User::factory()->proctor()->create();
        $newProctor = User::factory()->proctor()->create();
        $trainingClass = TrainingClass::factory()->create(['class_number' => 'REASSIGN-PROCTOR-001', 'proctor_id' => $oldProctor->id]);
        $student = User::factory()->student()->create();
        Enrollment::create(['class_id' => $trainingClass->id, 'student_user_id' => $student->id, 'status' => 'enrolled', 'enrolled_at' => now()]);

        $this->actingAs($oldProctor)->withSession(['auth.session_version' => $oldProctor->session_version])
            ->get(route('proctor.dashboard'))->assertOk()->assertSee('REASSIGN-PROCTOR-001');

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->put(route('admin.classes.update', $trainingClass), [
                'class_number' => $trainingClass->class_number,
                'course_id' => $trainingClass->course_id,
                'proctor_id' => $newProctor->id,
                'instructor_id' => $trainingClass->instructor_id,
            ])->assertRedirect();

        $this->actingAs($oldProctor)->withSession(['auth.session_version' => $oldProctor->session_version])
            ->get(route('proctor.dashboard'))->assertOk()->assertDontSee('REASSIGN-PROCTOR-001');

        $this->actingAs($newProctor)->withSession(['auth.session_version' => $newProctor->session_version])
            ->get(route('proctor.dashboard'))->assertOk()->assertSee('REASSIGN-PROCTOR-001');

        // Enrollment/roster data is untouched by the reassignment.
        $this->assertDatabaseHas('enrollments', ['class_id' => $trainingClass->id, 'student_user_id' => $student->id, 'status' => 'enrolled']);
    }

    public function test_reassigning_the_instructor_removes_old_access_and_grants_new_access(): void
    {
        $admin = User::factory()->admin()->create();
        $oldInstructor = User::factory()->instructor()->create();
        $newInstructor = User::factory()->instructor()->create();
        $trainingClass = TrainingClass::factory()->create(['class_number' => 'REASSIGN-INSTRUCTOR-001', 'instructor_id' => $oldInstructor->id]);

        $this->actingAs($oldInstructor)->withSession(['auth.session_version' => $oldInstructor->session_version])
            ->get(route('instructor.dashboard'))->assertOk()->assertSee('REASSIGN-INSTRUCTOR-001');

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->put(route('admin.classes.update', $trainingClass), [
                'class_number' => $trainingClass->class_number,
                'course_id' => $trainingClass->course_id,
                'proctor_id' => $trainingClass->proctor_id,
                'instructor_id' => $newInstructor->id,
            ])->assertRedirect();

        $this->actingAs($oldInstructor)->withSession(['auth.session_version' => $oldInstructor->session_version])
            ->get(route('instructor.dashboard'))->assertOk()->assertDontSee('REASSIGN-INSTRUCTOR-001');

        $this->actingAs($newInstructor)->withSession(['auth.session_version' => $newInstructor->session_version])
            ->get(route('instructor.dashboard'))->assertOk()->assertSee('REASSIGN-INSTRUCTOR-001');
    }
}
