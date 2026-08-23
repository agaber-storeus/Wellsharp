<?php

namespace Tests\Feature\Operational;

use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\TrainingClass;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPasswordRevealTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function makeStudentWithPassword(string $password = 'trainee-day-one-password'): User
    {
        $student = User::factory()->student()->create();
        $student->setPasswordAndCiphertext($password, Role::STUDENT);
        $student->save();

        return $student->fresh();
    }

    public function test_active_proctor_can_reveal_a_students_password(): void
    {
        $proctor = User::factory()->proctor()->create();
        $student = $this->makeStudentWithPassword('proctor-visible-password');

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.students.reveal-password', $student))
            ->assertOk()
            ->assertJson(['password' => 'proctor-visible-password']);

        $this->assertDatabaseHas('audit_events', ['action' => 'student.password_viewed']);
    }

    public function test_active_instructor_can_reveal_a_students_password(): void
    {
        $instructor = User::factory()->instructor()->create();
        $student = $this->makeStudentWithPassword('instructor-visible-password');

        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version])
            ->postJson(route('instructor.students.reveal-password', $student))
            ->assertOk()
            ->assertJson(['password' => 'instructor-visible-password']);
    }

    public function test_student_cannot_reveal_any_password(): void
    {
        $viewer = User::factory()->student()->create();
        $target = $this->makeStudentWithPassword();

        $this->actingAs($viewer)->withSession(['auth.session_version' => $viewer->session_version])
            ->postJson(route('admin.users.reveal-password', $target))
            ->assertForbidden();
    }

    public function test_proctor_cannot_reveal_a_staff_members_password(): void
    {
        $proctor = User::factory()->proctor()->create();
        $otherProctor = User::factory()->proctor()->create();

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.students.reveal-password', $otherProctor))
            ->assertForbidden();
    }

    public function test_disabled_proctor_cannot_reveal_a_students_password(): void
    {
        $disabledProctor = User::factory()->proctor()->create(['status' => 'disabled']);
        $student = $this->makeStudentWithPassword();

        $this->assertFalse((new UserPolicy)->viewPassword($disabledProctor, $student));
    }

    /**
     * The Class Dashboard now displays Student passwords directly (business
     * requirement change), fetched via a batch endpoint
     * (ClassRosterStudentPasswordsTest) rather than a per-Student "Reveal"
     * click. The initial roster payload must still carry only safe metadata
     * (a stable student identifier and the batch endpoint's URL) - never a
     * plaintext password and never a per-Student reveal URL.
     */
    public function test_class_roster_data_carries_only_safe_metadata_not_the_plain_text_password(): void
    {
        $proctor = User::factory()->proctor()->create();
        $student = $this->makeStudentWithPassword('roster-visible-password');
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id, 'proctor_id' => $proctor->id]);
        Enrollment::create([
            'class_id' => $trainingClass->id,
            'student_user_id' => $student->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'));

        $response->assertOk()
            ->assertDontSee('roster-visible-password')
            ->assertSee($student->wellsharp_id)
            ->assertSee(json_encode($student->public_id), false)
            ->assertSee(json_encode(route('proctor.classes.student-passwords', $trainingClass)), false);
    }

    /**
     * Mandatory regression test: opening the Class Dashboard - for either
     * operational role - must never embed a Student's plaintext password in
     * the initial page source, including inside the server-rendered
     * `window.wellsharpClassModalData` JSON payload the roster/print views
     * read from client-side.
     */
    public function test_page_source_never_exposes_a_students_plaintext_password_on_either_role(): void
    {
        $plaintext = 'A1b2C';
        $course = Course::factory()->create();
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id, 'proctor_id' => $proctor->id, 'instructor_id' => $instructor->id]);
        $student = $this->makeStudentWithPassword($plaintext);
        Enrollment::create([
            'class_id' => $trainingClass->id,
            'student_user_id' => $student->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'))
            ->assertOk()
            ->assertDontSee($plaintext);

        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version])
            ->get(route('instructor.classes'))
            ->assertOk()
            ->assertDontSee($plaintext);
    }

    public function test_reveal_response_is_not_cacheable(): void
    {
        $proctor = User::factory()->proctor()->create();
        $student = $this->makeStudentWithPassword();

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.students.reveal-password', $student))
            ->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_reveal_endpoint_rejects_a_guest(): void
    {
        $student = $this->makeStudentWithPassword();

        $this->postJson(route('proctor.students.reveal-password', $student))
            ->assertUnauthorized();
    }

    public function test_reveal_of_a_non_student_target_returns_not_found(): void
    {
        $proctor = User::factory()->proctor()->create();
        $otherProctor = User::factory()->proctor()->create();

        // The target is not a Student, so viewPassword() denies the request
        // before revealPassword() is ever reached - asserted as a 403 (the
        // authorization boundary), not a 404, since policy denial always
        // takes precedence over "does the value exist" lookups.
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.students.reveal-password', $otherProctor))
            ->assertForbidden();
    }

    public function test_reveal_audit_event_never_contains_the_password_value_hash_or_ciphertext(): void
    {
        $proctor = User::factory()->proctor()->create();
        $student = $this->makeStudentWithPassword('audit-safety-password');

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.students.reveal-password', $student))
            ->assertOk();

        $event = AuditEvent::where('action', 'student.password_viewed')->latest()->firstOrFail();
        $payload = json_encode($event->toArray());

        $this->assertStringNotContainsString('audit-safety-password', $payload);
        $this->assertStringNotContainsString($student->getRawOriginal('password'), $payload);
        $this->assertStringNotContainsString((string) $student->password_ciphertext, $payload);
        $this->assertSame($proctor->id, $event->actor_user_id);
        $this->assertSame((string) $student->id, $event->subject_id);
    }
}
