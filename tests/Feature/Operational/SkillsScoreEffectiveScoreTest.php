<?php

namespace Tests\Feature\Operational;

use App\Actions\Certificates\IssueCertificateAction;
use App\Actions\Classes\UpdateEnrollmentSkillsScoreAction;
use App\Models\AuditEvent;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\TrainingClass;
use App\Models\User;
use App\Policies\EnrollmentPolicy;
use App\Services\EffectiveScoreService;
use App\Services\OperationalClassMapPointBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skills Score is a manual override of a trainee's final/effective
 * percentage - not an independent informational score. These tests cover
 * the centralized EffectiveScoreService formula, the certificate eligibility
 * gate that now depends on it, the update/clear endpoint, authorization, and
 * the audit trail. See the "Skills Score" rework requested in this session.
 */
class SkillsScoreEffectiveScoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{score: float, skillsScore: ?int, passed: bool}
     */
    private function resolve(float $knowledgeScore, ?int $skillsScore, int $passingScore): array
    {
        $result = app(EffectiveScoreService::class)->resolve($knowledgeScore, $skillsScore, $passingScore);

        return ['score' => $result['score'], 'skillsScore' => $result['skills_score'], 'passed' => $result['passed']];
    }

    public function test_effective_score_falls_back_to_knowledge_exam_when_no_override(): void
    {
        $result = $this->resolve(30.0, null, 70);
        $this->assertSame(30.0, $result['score']);
        $this->assertFalse($result['passed']);
    }

    public function test_skills_score_equal_to_threshold_passes(): void
    {
        $result = $this->resolve(30.0, 70, 70);
        $this->assertSame(70.0, $result['score']);
        $this->assertTrue($result['passed']);
    }

    public function test_skills_score_of_100_overrides_a_failing_exam_to_passing(): void
    {
        $result = $this->resolve(30.0, 100, 70);
        $this->assertSame(100.0, $result['score']);
        $this->assertTrue($result['passed']);
    }

    public function test_skills_score_of_50_overrides_a_passing_exam_to_failing(): void
    {
        $result = $this->resolve(90.0, 50, 70);
        $this->assertSame(50.0, $result['score']);
        $this->assertFalse($result['passed']);
    }

    public function test_skills_score_of_zero_is_a_legitimate_override_and_fails(): void
    {
        $result = $this->resolve(90.0, 0, 70);
        $this->assertSame(0.0, $result['score']);
        $this->assertFalse($result['passed']);
    }

    public function test_knowledge_score_equal_to_threshold_passes_without_override(): void
    {
        $result = $this->resolve(70.0, null, 70);
        $this->assertSame(70.0, $result['score']);
        $this->assertTrue($result['passed']);
    }

    public function test_certificate_eligibility_uses_the_effective_score_end_to_end(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 3);

        $noCert = app(IssueCertificateAction::class)->execute($data['attempt']);
        $this->assertNull($noCert, 'A 30% raw score against a 70% threshold must not issue a certificate.');
        $this->assertDatabaseHas('exam_attempts', ['id' => $data['attempt']->id, 'score' => 30, 'passed' => 0]);

        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment'], 75);

        $certificate = Certificate::query()->where('exam_attempt_id', $data['attempt']->id)->first();
        $this->assertNotNull($certificate, 'A Skills Score override above the threshold must issue a certificate.');
        $this->assertSame(75.0, (float) $certificate->score);
        $this->assertSame(70, $certificate->passing_score);
        // The raw Knowledge Exam result is never touched by the override.
        $this->assertDatabaseHas('exam_attempts', ['id' => $data['attempt']->id, 'score' => 30, 'passed' => 0]);
    }

    public function test_passing_exam_overridden_to_failing_does_not_retract_an_already_issued_certificate(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 9);
        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);
        $this->assertNotNull($certificate);
        $this->assertSame(90.0, (float) $certificate->score);

        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment'], 50);

        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'score' => 90]);
        $this->assertDatabaseCount('certificates', 1);

        // Future eligibility decisions (e.g. a later re-issuance attempt) must
        // now see the trainee as failing, even though the old certificate stands.
        $again = app(IssueCertificateAction::class)->execute($data['attempt']->fresh());
        $this->assertNull($again);
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_clearing_an_override_falls_back_to_the_knowledge_exam_result(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 3);
        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment'], 80);
        $this->assertDatabaseCount('certificates', 1);

        // Clearing back to null must be distinguishable from entering 0.
        $enrollment = app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment']->fresh(), null);
        $this->assertNull($enrollment->skills_score);

        $again = app(IssueCertificateAction::class)->execute($data['attempt']->fresh());
        $this->assertNull($again, 'With the override cleared, the raw 30% result no longer qualifies.');
        // The certificate issued while the override was active is still untouched.
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_updating_skills_score_via_http_supports_boundary_values(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);
        $proctor = User::factory()->proctor()->create();
        $data['trainingClass']->update(['proctor_id' => $proctor->id]);
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version]);

        $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 0])
            ->assertOk()->assertJsonPath('skills_score', 0);
        $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 100])
            ->assertOk()->assertJsonPath('skills_score', 100);
        $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => -1])
            ->assertStatus(422)->assertJsonValidationErrors('skills_score');
        $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 101])
            ->assertStatus(422)->assertJsonValidationErrors('skills_score');
        $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 'not-a-number'])
            ->assertStatus(422)->assertJsonValidationErrors('skills_score');
        $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => null])
            ->assertOk()->assertJsonPath('skills_score', null);
    }

    /**
     * The Skills Score endpoint response drives the Class Dashboard roster's
     * Certificate cell (Alpine.js) without a page reload - it must return the
     * effective score, pass/fail, and reconciled certificate state, not just
     * the raw skills_score column.
     */
    public function test_http_response_reflects_certificate_when_a_failed_exam_becomes_passing(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 3); // 30%
        $proctor = User::factory()->proctor()->create();
        $data['trainingClass']->update(['proctor_id' => $proctor->id]);

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 75])
            ->assertOk();

        $this->assertSame(75, $response->json('skills_score'));
        $this->assertSame(75.0, (float) $response->json('effective_score'));
        $this->assertTrue($response->json('passed'));
        $this->assertTrue($response->json('overridden'));
        $this->assertNotNull($response->json('certificate_download_url'), 'A newly-passing override must reconcile and issue a certificate.');
        $this->assertNotNull($response->json('certificate_number'));
    }

    /**
     * The operational Certificate column always reflects the trainee's
     * *current* effective result. An already-issued Certificate row is never
     * deleted (BR-031/BR-033 - no revocation workflow exists), but once an
     * override drops the trainee below the passing score this endpoint must
     * stop exposing it as the row's current certificate, so the Alpine-bound
     * Certificate cell hides it without a page reload.
     */
    public function test_http_response_hides_the_certificate_when_a_passing_exam_becomes_failing(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 9); // 90%
        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);
        $this->assertNotNull($certificate);

        $proctor = User::factory()->proctor()->create();
        $data['trainingClass']->update(['proctor_id' => $proctor->id]);
        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 50])
            ->assertOk();

        $this->assertSame(50, $response->json('skills_score'));
        $this->assertSame(50.0, (float) $response->json('effective_score'));
        $this->assertFalse($response->json('passed'));
        $this->assertTrue($response->json('overridden'));
        $this->assertNull($response->json('certificate_download_url'));
        $this->assertNull($response->json('certificate_front_url'));
        $this->assertNull($response->json('certificate_back_url'));
        $this->assertNull($response->json('certificate_number'));

        // The historical Certificate row is preserved for audit purposes -
        // only the operational roster's "current certificate" view hides it.
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'score' => 90]);
        $this->assertDatabaseCount('certificates', 1);
    }

    /**
     * Regression coverage for toggling Skills Score back and forth: the
     * Certificate cell must hide/show consistently on every transition, and
     * IssueCertificateAction's per-attempt idempotency must never create a
     * second Certificate row when the trainee passes again.
     */
    public function test_certificate_visibility_toggles_correctly_across_repeated_skills_score_changes(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 9); // 90%
        $proctor = User::factory()->proctor()->create();
        $data['trainingClass']->update(['proctor_id' => $proctor->id]);
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version]);

        // No override yet: raw 90% already passes, so the first Skills Score
        // write (even to null) reconciles and issues a certificate.
        $response = $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => null])->assertOk();
        $this->assertTrue($response->json('passed'));
        $this->assertNotNull($response->json('certificate_download_url'));
        $this->assertDatabaseCount('certificates', 1);
        $firstCertificateNumber = $response->json('certificate_number');

        // Override drops it below the threshold: certificate must disappear
        // from the row, but the underlying record stays untouched.
        $response = $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 50])->assertOk();
        $this->assertFalse($response->json('passed'));
        $this->assertNull($response->json('certificate_download_url'));
        $this->assertNull($response->json('certificate_number'));
        $this->assertDatabaseCount('certificates', 1);

        // Overriding back above the threshold must reuse the existing
        // Certificate row (IssueCertificateAction is idempotent per attempt),
        // never create a duplicate.
        $response = $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 80])->assertOk();
        $this->assertTrue($response->json('passed'));
        $this->assertNotNull($response->json('certificate_download_url'));
        $this->assertSame($firstCertificateNumber, $response->json('certificate_number'));
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_http_response_reflects_certificate_after_clearing_a_passing_override(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 3); // 30%
        $proctor = User::factory()->proctor()->create();
        $data['trainingClass']->update(['proctor_id' => $proctor->id]);
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 75])
            ->assertOk();

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => null])
            ->assertOk();

        $this->assertNull($response->json('skills_score'));
        $this->assertSame(30.0, (float) $response->json('effective_score'));
        $this->assertFalse($response->json('passed'));
        $this->assertFalse($response->json('overridden'));
        // A certificate was issued while the override was active (30% never
        // qualifies on its own) - clearing back to the raw failing result
        // must hide it from the row even though the record still exists.
        $this->assertNull($response->json('certificate_download_url'));
        $this->assertNull($response->json('certificate_number'));
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_http_response_reflects_certificate_after_clearing_a_failing_override(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 9); // 90%
        $proctor = User::factory()->proctor()->create();
        $data['trainingClass']->update(['proctor_id' => $proctor->id]);
        $before = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 50])
            ->assertOk();

        $this->assertNull($before->json('certificate_download_url'), 'The override drops a 90% raw result below the 70% threshold - no certificate should show yet.');

        $response = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => null])
            ->assertOk();

        $this->assertNull($response->json('skills_score'));
        $this->assertSame(90.0, (float) $response->json('effective_score'));
        $this->assertTrue($response->json('passed'));
        $this->assertFalse($response->json('overridden'));
        $this->assertNotNull($response->json('certificate_download_url'), 'Clearing back to a passing knowledge score must reconcile and issue a certificate.');
        $this->assertNotNull($response->json('certificate_number'));
    }

    public function test_admin_active_proctor_and_active_instructor_can_update_skills_score(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);
        $admin = User::factory()->admin()->create();
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $data['trainingClass']->update(['proctor_id' => $proctor->id, 'instructor_id' => $instructor->id]);

        // Admin has no dedicated `admin.enrollments.skills-score` route today
        // (this endpoint only exists under the Proctor/Instructor operational
        // surface); Admin authorization is still real, via EnrollmentPolicy's
        // blanket `before()` override, verified directly here.
        $this->assertTrue((new EnrollmentPolicy)->before($admin));

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 60])
            ->assertOk();

        $this->actingAs($instructor)->withSession(['auth.session_version' => $instructor->session_version])
            ->postJson(route('instructor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 65])
            ->assertOk();
    }

    public function test_student_cannot_update_skills_score(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);

        $this->actingAs($data['student'])->withSession(['auth.session_version' => $data['student']->session_version])
            ->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 90])
            ->assertForbidden();

        $this->assertDatabaseHas('enrollments', ['id' => $data['enrollment']->id, 'skills_score' => null]);
    }

    public function test_audit_event_records_before_and_after_including_clearing(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);

        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment'], 82);
        $this->assertDatabaseHas('audit_events', ['action' => 'enrollment.skills_score_updated']);
        $latest = AuditEvent::query()->where('action', 'enrollment.skills_score_updated')->latest('id')->first();
        $this->assertSame(['skills_score' => null], $latest->before_state);
        $this->assertSame(['skills_score' => 82], $latest->after_state);

        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment']->fresh(), null);
        $latest = AuditEvent::query()->where('action', 'enrollment.skills_score_updated')->latest('id')->first();
        $this->assertSame(['skills_score' => 82], $latest->before_state);
        $this->assertSame(['skills_score' => null], $latest->after_state);
    }

    /**
     * Reproduces the reported "reopen shows the old value" bug at the
     * persistence/retrieval layer: repeated writes to the SAME Enrollment
     * row, each verified against the raw database and a freshly re-queried
     * model - not the in-memory instance the previous write returned.
     */
    public function test_repeated_updates_persist_the_latest_value_in_the_database(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);
        $action = app(UpdateEnrollmentSkillsScoreAction::class);
        $enrollment = $data['enrollment'];

        foreach ([70, 85, 50, null, 100] as $value) {
            $enrollment = $action->execute($enrollment->fresh(), $value);

            $this->assertDatabaseHas('enrollments', ['id' => $data['enrollment']->id, 'skills_score' => $value]);
            $fresh = Enrollment::query()->findOrFail($data['enrollment']->id);
            $this->assertSame($value, $fresh->skills_score, "Fresh query did not reflect skills_score = {$this->describe($value)}.");
        }
    }

    /**
     * The HTTP response after a second update must reflect the SECOND
     * value, not the first - proving the controller builds its response
     * from post-save database state rather than from stale input/state.
     */
    public function test_response_reflects_the_latest_update_not_a_previous_one(): void
    {
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);
        $proctor = User::factory()->proctor()->create();
        $data['trainingClass']->update(['proctor_id' => $proctor->id]);
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version]);

        $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 70])->assertOk();
        $response = $this->postJson(route('proctor.enrollments.skills-score', $data['enrollment']), ['skills_score' => 85])->assertOk();

        $response->assertJsonPath('skills_score', 85);
    }

    /**
     * Calling the row builder again after two updates - independent of the
     * request/response that performed them - must see the latest value.
     * This isolates OperationalClassMapPointBuilder from any controller-level
     * staleness.
     */
    public function test_fresh_builder_retrieval_reflects_the_latest_update(): void
    {
        $this->actingAs(User::factory()->proctor()->create());
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);
        $action = app(UpdateEnrollmentSkillsScoreAction::class);

        $action->execute($data['enrollment'], 70);
        $action->execute($data['enrollment']->fresh(), 85);

        $row = app(OperationalClassMapPointBuilder::class)
            ->scoreRowForEnrollment(Enrollment::query()->findOrFail($data['enrollment']->id));

        $this->assertSame(85, $row['skillsScore']);
    }

    /**
     * The close/reopen equivalent at the backend layer: rebuild the Class
     * Dashboard's modal payload from a completely fresh TrainingClass query
     * (as a new page load / reopened modal would), after each of several
     * updates, and confirm it never shows a value older than the latest
     * write. This is the scenario the reported bug actually reproduced -
     * the database and scoreRowForEnrollment() were already correct, but the
     * cached client-side roster snapshot the modal reopens from was not
     * being updated after a save. See the fix in
     * public/js/proctor-class-modal-laravel.js (classModalData[...].scoreRows
     * sync in saveScore()) - this test proves the backend half: every fresh
     * rebuild the frontend could possibly reload from is correct.
     */
    public function test_reopening_the_class_dashboard_rebuilds_the_latest_skills_score(): void
    {
        $this->actingAs(User::factory()->proctor()->create());
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);
        $builder = app(OperationalClassMapPointBuilder::class);

        $this->assertNull($this->reopenScoreRow($builder, $data['trainingClass']->id)['skillsScore']);

        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment'], 70);
        $this->assertSame(70, $this->reopenScoreRow($builder, $data['trainingClass']->id)['skillsScore']);

        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment']->fresh(), 85);
        $this->assertSame(85, $this->reopenScoreRow($builder, $data['trainingClass']->id)['skillsScore']);
    }

    public function test_clearing_the_override_does_not_resurrect_the_old_value_on_reopen(): void
    {
        $this->actingAs(User::factory()->proctor()->create());
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);
        $builder = app(OperationalClassMapPointBuilder::class);

        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment'], 70);
        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment']->fresh(), null);

        $this->assertNull($this->reopenScoreRow($builder, $data['trainingClass']->id)['skillsScore']);
    }

    /**
     * `0` is a legitimate override (a real failing score, distinct from "no
     * override") and must never be lost or coerced to null on reopen -
     * Enrollment::casts() must not turn a stored 0 into an empty value, and
     * the response/row-building path must not treat it as falsy.
     */
    public function test_zero_survives_persistence_and_reopening(): void
    {
        $this->actingAs(User::factory()->proctor()->create());
        $data = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);
        $builder = app(OperationalClassMapPointBuilder::class);

        app(UpdateEnrollmentSkillsScoreAction::class)->execute($data['enrollment'], 0);

        $this->assertSame(0, $this->reopenScoreRow($builder, $data['trainingClass']->id)['skillsScore']);
    }

    /**
     * Skills Score is scoped per Enrollment, not per Student - a Student
     * with two Enrollments (two Classes) must have them updated and read
     * completely independently.
     */
    public function test_updating_one_enrollment_does_not_affect_another_enrollment_for_the_same_student(): void
    {
        $this->actingAs(User::factory()->proctor()->create());
        $dataA = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5);
        $dataB = $this->makeSubmittedAttempt(passingScore: 70, correctCount: 5, student: $dataA['student']);

        $action = app(UpdateEnrollmentSkillsScoreAction::class);
        $action->execute($dataA['enrollment'], 40);
        $action->execute($dataB['enrollment'], 90);

        $builder = app(OperationalClassMapPointBuilder::class);
        $rowA = $builder->scoreRowForEnrollment(Enrollment::query()->findOrFail($dataA['enrollment']->id));
        $rowB = $builder->scoreRowForEnrollment(Enrollment::query()->findOrFail($dataB['enrollment']->id));

        $this->assertSame(40, $rowA['skillsScore']);
        $this->assertSame(90, $rowB['skillsScore']);
    }

    private function describe(?int $value): string
    {
        return $value === null ? 'null' : (string) $value;
    }

    /** Rebuilds one Class's roster row from a completely fresh TrainingClass query - the backend equivalent of closing and reopening the Class Dashboard modal. */
    private function reopenScoreRow(OperationalClassMapPointBuilder $builder, int $trainingClassId): array
    {
        $classes = TrainingClass::query()
            ->with(['enrollments.student.profile', 'examSchedules.exam', 'examSchedules.attempts.student.profile', 'examSchedules.attempts.exam'])
            ->whereKey($trainingClassId)
            ->get();

        $trainingClass = $classes->first();

        return $builder->buildModalData($classes)[$trainingClass->public_id]['scoreRows'][0];
    }

    /**
     * @return array{attempt: ExamAttempt, enrollment: Enrollment, exam: Exam, student: User, trainingClass: TrainingClass}
     */
    private function makeSubmittedAttempt(int $passingScore, int $correctCount, int $totalQuestions = 10, ?User $student = null): array
    {
        $this->seedRoles();
        $student = $student ?: User::factory()->student()->create();
        $course = Course::factory()->create();
        $exam = Exam::create([
            'course_id' => $course->id,
            'name' => 'Effective Score Exam',
            'passing_score' => $passingScore,
            'question_order_mode' => 'static',
            'status' => 'published',
        ]);
        $group = Group::create(['name' => 'Effective Score Group', 'status' => 'active']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id, 'status' => 'active']);
        $enrollment = Enrollment::create([
            'class_id' => $trainingClass->id,
            'student_user_id' => $student->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
        $schedule = ExamSchedule::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'training_class_id' => $trainingClass->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'duration_minutes' => 90,
            'status' => 'scheduled',
        ]);
        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'exam_schedule_id' => $schedule->id,
            'student_user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now()->subMinutes(2),
        ]);

        for ($index = 0; $index < $totalQuestions; $index++) {
            $question = Question::create([
                'course_id' => $course->id,
                'question_text' => "Effective score question {$index}",
                'type' => 'true_false',
                'difficulty' => 'easy',
                'default_marks' => 1,
                'correct_answer_boolean' => true,
            ]);
            ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'display_order' => $index + 1, 'points' => 1]);
            ExamAttemptQuestion::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'display_order' => $index + 1,
                'points' => 1,
                'answer' => $index < $correctCount ? 'true' : 'false',
                'answered_at' => now()->subMinutes(5),
            ]);
        }

        return compact('attempt', 'enrollment', 'exam', 'student', 'trainingClass');
    }
}
