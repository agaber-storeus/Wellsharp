<?php

namespace Tests\Feature\Operational;

use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassRosterStudentPasswordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function enroll(TrainingClass $trainingClass, User $student): void
    {
        Enrollment::create([
            'class_id' => $trainingClass->id,
            'student_user_id' => $student->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
    }

    private function makeStudentWithPassword(string $password): User
    {
        $student = User::factory()->student()->create();
        $student->setPasswordAndCiphertext($password, Role::STUDENT);
        $student->save();

        return $student->fresh();
    }

    public function test_active_proctor_receives_batch_passwords_for_their_classes_roster(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);
        $studentA = $this->makeStudentWithPassword('roster-password-a');
        $studentB = $this->makeStudentWithPassword('roster-password-b');
        $this->enroll($trainingClass, $studentA);
        $this->enroll($trainingClass, $studentB);

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.student-passwords', $trainingClass))
            ->assertOk();

        $byStudentId = collect($response->json('students'))->keyBy('student_id');
        $this->assertSame('roster-password-a', $byStudentId->get($studentA->public_id)['password']);
        $this->assertSame('roster-password-b', $byStudentId->get($studentB->public_id)['password']);
    }

    public function test_active_instructor_receives_batch_passwords_for_their_classes_roster(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);
        $student = $this->makeStudentWithPassword('instructor-batch-password');
        $this->enroll($trainingClass, $student);

        $response = $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version])
            ->postJson(route('instructor.classes.student-passwords', $trainingClass))
            ->assertOk();

        $byStudentId = collect($response->json('students'))->keyBy('student_id');
        $this->assertSame('instructor-batch-password', $byStudentId->get($student->public_id)['password']);
    }

    public function test_student_from_an_unrelated_class_is_not_included(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create();

        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);
        $member = $this->makeStudentWithPassword('belongs-here-password');
        $this->enroll($trainingClass, $member);

        $otherClass = TrainingClass::factory()->create(['course_id' => $course->id]);
        $stranger = $this->makeStudentWithPassword('unrelated-stranger-password');
        $this->enroll($otherClass, $stranger);

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.student-passwords', $trainingClass))
            ->assertOk();

        $studentIds = collect($response->json('students'))->pluck('student_id');
        $this->assertTrue($studentIds->contains($member->public_id));
        $this->assertFalse($studentIds->contains($stranger->public_id));
    }

    public function test_disabled_staff_cannot_load_class_passwords(): void
    {
        $disabledProctor = User::factory()->proctor()->create(['status' => 'disabled']);
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);

        $this->actingAs($disabledProctor)->withSession(['auth.session_version' => $disabledProctor->session_version])
            ->postJson(route('proctor.classes.student-passwords', $trainingClass))
            ->assertForbidden();
    }

    public function test_disabled_instructor_cannot_load_class_passwords(): void
    {
        $disabledInstructor = User::factory()->instructor()->create(['status' => 'disabled']);
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);

        $this->actingAs($disabledInstructor)->withSession(['auth.session_version' => $disabledInstructor->session_version])
            ->postJson(route('instructor.classes.student-passwords', $trainingClass))
            ->assertForbidden();
    }

    public function test_archived_staff_cannot_load_class_passwords(): void
    {
        $archivedProctor = User::factory()->proctor()->archived()->create();
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);

        $this->actingAs($archivedProctor)->withSession(['auth.session_version' => $archivedProctor->session_version])
            ->postJson(route('proctor.classes.student-passwords', $trainingClass))
            ->assertForbidden();
    }

    public function test_guest_cannot_load_class_passwords(): void
    {
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);

        $this->postJson(route('proctor.classes.student-passwords', $trainingClass))
            ->assertUnauthorized();
    }

    public function test_student_role_cannot_load_class_passwords(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);

        // Students only reach role-gated routes at all if role middleware
        // matched; here the route itself is registered only under
        // proctor/instructor prefixes, so a Student is rejected outright.
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])
            ->postJson(route('proctor.classes.student-passwords', $trainingClass))
            ->assertForbidden();
    }

    public function test_missing_password_ciphertext_returns_null_safely(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);

        // Legacy Student: has a login password hash but no recoverable
        // ciphertext (created before this feature, or role-changed away and
        // back without a password reset in between). UserFactory now sets a
        // ciphertext for every Student by default (matching CreateUserAction),
        // so this scenario is built explicitly instead of relying on a gap.
        $legacyStudent = User::factory()->student()->create();
        $legacyStudent->forceFill(['password_ciphertext' => null])->save();
        $this->enroll($trainingClass, $legacyStudent);

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.student-passwords', $trainingClass))
            ->assertOk();

        $byStudentId = collect($response->json('students'))->keyBy('student_id');
        $this->assertNull($byStudentId->get($legacyStudent->public_id)['password']);
    }

    public function test_batch_reveal_creates_one_audit_event_for_the_whole_roster_without_password_values(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);
        $studentA = $this->makeStudentWithPassword('audit-safety-a');
        $studentB = $this->makeStudentWithPassword('audit-safety-b');
        $this->enroll($trainingClass, $studentA);
        $this->enroll($trainingClass, $studentB);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.student-passwords', $trainingClass))
            ->assertOk();

        $this->assertDatabaseCount('audit_events', 1);
        $event = AuditEvent::where('action', 'student_passwords.class_roster_viewed')->firstOrFail();
        $this->assertSame($proctor->id, $event->actor_user_id);
        $this->assertSame((string) $trainingClass->id, $event->subject_id);

        $payload = json_encode($event->toArray());
        $this->assertStringNotContainsString('audit-safety-a', $payload);
        $this->assertStringNotContainsString('audit-safety-b', $payload);
        $this->assertStringNotContainsString((string) $studentA->password_ciphertext, $payload);
        $this->assertStringNotContainsString((string) $studentB->password_ciphertext, $payload);
        $this->assertStringNotContainsString($studentA->getRawOriginal('password'), $payload);
        $this->assertStringContainsString('"student_count":2', $payload);
    }

    public function test_response_is_not_cacheable(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create();
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.classes.student-passwords', $trainingClass))
            ->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_batch_lookup_does_not_grow_query_count_with_roster_size(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create();

        $smallClass = TrainingClass::factory()->create(['course_id' => $course->id]);
        foreach (range(1, 3) as $i) {
            $this->enroll($smallClass, $this->makeStudentWithPassword("small-{$i}"));
        }

        $largeClass = TrainingClass::factory()->create(['course_id' => $course->id]);
        foreach (range(1, 15) as $i) {
            $this->enroll($largeClass, $this->makeStudentWithPassword("large-{$i}"));
        }

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version]);
        // Prime the acting user's own currentRole relation before measuring.
        // EnsureCurrentRole (unrelated to this endpoint's roster logic) lazily
        // loads $proctor->currentRole on first touch; since this same $proctor
        // instance is reused for both requests below, leaving it unprimed
        // would make only the *first* call "cold" and count one extra query
        // regardless of which class is requested first - an ordering
        // artifact, not per-student growth. Priming it isolates the
        // assertion to genuine roster-size-driven query growth.
        $proctor->loadMissing('currentRole');

        $smallQueryCount = $this->countQueries(fn () => $this->postJson(route('proctor.classes.student-passwords', $smallClass))->assertOk());
        $largeQueryCount = $this->countQueries(fn () => $this->postJson(route('proctor.classes.student-passwords', $largeClass))->assertOk());

        $this->assertSame($smallQueryCount, $largeQueryCount, 'Query count must not grow with roster size (no N+1).');
    }

    private function countQueries(\Closure $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $callback();

        return $count;
    }
}
