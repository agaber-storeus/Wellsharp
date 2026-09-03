<?php

namespace Tests\Feature\Admin;

use App\Actions\Certificates\IssueCertificateAction;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use App\Services\CertificateQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CertificateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_passing_attempt_issues_one_certificate_with_snapshots(): void
    {
        $data = $this->makeAttempt('submitted', true);

        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);

        $this->assertNotNull($certificate);
        $this->assertSame(100.0, (float) $certificate->score);
        $this->assertSame($data['student']->display_name, $certificate->student_name);
        $this->assertSame($data['course']->name, $certificate->subject_name);
        $this->assertDatabaseHas('certificates', [
            'exam_attempt_id' => $data['attempt']->id,
            'status' => 'issued',
        ]);
        $this->assertDatabaseCount('certificate_documents', 4);
        $this->assertDatabaseHas('certificate_documents', ['type' => 'knowledge_assessment_report']);
        $this->assertDatabaseHas('certificate_documents', ['type' => 'completion_card_front']);
        $this->assertDatabaseHas('certificate_documents', ['type' => 'completion_card_back']);
        $this->assertDatabaseHas('certificate_documents', ['type' => 'full_certificate']);

        $this->assertSame($certificate->id, app(IssueCertificateAction::class)->execute($data['attempt'])?->id);
        $this->assertDatabaseCount('certificates', 1);
        $this->assertDatabaseCount('certificate_documents', 4);
    }

    public function test_failed_attempt_is_scored_but_does_not_issue_certificate(): void
    {
        $data = $this->makeAttempt('submitted', false);

        $this->assertNull(app(IssueCertificateAction::class)->execute($data['attempt']));
        $this->assertDatabaseHas('exam_attempts', ['id' => $data['attempt']->id, 'passed' => 0]);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_public_certificate_lookup_and_verification_show_snapshot_status_without_private_fields(): void
    {
        $data = $this->makeAttempt('submitted', true);
        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);

        $this->get(route('iadc.certification', ['lookup' => strtolower($certificate->certificate_number)]))
            ->assertRedirect(route('certificates.verify', $certificate->certificate_number));

        $this->get(route('certificates.verify', $certificate->certificate_number))
            ->assertOk()
            ->assertSee($certificate->certificate_number)
            ->assertSee($certificate->student_name)
            ->assertSee('Issued')
            ->assertDontSee($certificate->student_email)
            ->assertDontSee($certificate->student_wellsharp_id);

        $certificate->update(['status' => 'revoked', 'revoked_at' => now(), 'revocation_reason' => 'Test revocation']);
        $this->get(route('certificates.verify', $certificate->certificate_number))
            ->assertOk()
            ->assertSee('Revoked');
    }

    public function test_public_instructor_lookup_lists_only_that_instructors_certificates(): void
    {
        $data = $this->makeAttempt('submitted', true);
        $instructor = User::factory()->instructor()->create();
        $data['trainingClass']->update(['instructor_id' => $instructor->id]);
        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);

        $otherCertificate = Certificate::factory()->create([
            'instructor_user_id' => User::factory()->instructor()->create()->id,
        ]);

        $this->get(route('iadc.certification', ['lookup' => strtolower($instructor->wellsharp_id)]))
            ->assertOk()
            ->assertSee($certificate->student_name)
            ->assertSee($certificate->certificate_number)
            ->assertDontSee($otherCertificate->certificate_number);
    }

    public function test_certificate_qr_code_targets_the_public_verification_route(): void
    {
        $data = $this->makeAttempt('submitted', true);
        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);
        $qrCodes = app(CertificateQrCodeService::class);

        $this->assertSame(route('certificates.verify', $certificate->certificate_number), $qrCodes->payload($certificate));
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $qrCodes->png($certificate));
    }

    public function test_admin_can_search_and_open_certificate_details(): void
    {
        $data = $this->makeAttempt('submitted', true);
        $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->get(route('admin.certificates.index', ['search' => $certificate->certificate_number]))
            ->assertOk()
            ->assertSee($certificate->certificate_number)
            ->assertSee($data['student']->display_name)
            ->assertSee('admin-shell')
            ->assertDontSee('css/proctor.css');

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->get(route('admin.certificates.show', $certificate))
            ->assertOk()
            ->assertSee($data['course']->name)
            ->assertSee($data['trainingClass']->class_number)
            ->assertSee($data['provider']->name)
            ->assertSee('admin-shell')
            ->assertDontSee('css/proctor.css');

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->get(route('certificates.show', $certificate))
            ->assertOk()
            ->assertSee('Certificate Bundle')
            ->assertSee('Completion Card - Front')
            ->assertSee('admin-shell')
            ->assertDontSee('css/proctor.css');

        $document = $certificate->documents()->firstOrFail();
        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->get(route('certificates.documents.show', [$certificate, $document]))
            ->assertOk()
            ->assertSee($document->title)
            ->assertSee('Download PDF');

        $pdfBytesByType = [];
        foreach ($certificate->documents as $downloadDocument) {
            $pdf = $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
                ->get(route('certificates.documents.download', [$certificate, $downloadDocument]));
            $pdf->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'attachment; filename="'.str($certificate->student_name.'-'.$downloadDocument->title)->slug('-').'.pdf"');
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
            $pdfBytesByType[$downloadDocument->type->value] = $pdf->getContent();
        }

        // The Knowledge Assessment Report has no template counterpart to the
        // wallet-card front/back, so it must render distinct content, not a
        // byte-for-byte copy of the completion card.
        $this->assertNotSame($pdfBytesByType['knowledge_assessment_report'], $pdfBytesByType['completion_card_front']);
        $this->assertNotSame($pdfBytesByType['knowledge_assessment_report'], $pdfBytesByType['completion_card_back']);

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->getJson(route('admin.certificates.data', [
                'search' => $data['student']->wellsharp_id,
                'status' => 'issued',
                'sort' => 'score',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.certificate_number', $certificate->certificate_number)
            ->assertJsonPath('data.0.status', 'issued')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_student_can_submit_attempt_and_trigger_certificate_issuance(): void
    {
        $data = $this->makeAttempt('in_progress', true);

        $this->actingAs($data['student'])->withSession(['auth.session_version' => $data['student']->session_version])
            ->post(route('student.attempts.submit', $data['attempt']))
            ->assertRedirect(route('student.attempts.report', $data['attempt']));

        $this->get(route('student.attempts.report', $data['attempt']))
            ->assertOk()
            ->assertSee('Knowledge Assessment Report')
            ->assertSee('your result submitted to the instructor')
            ->assertSee('Exit Exam')
            ->assertSee(route('logout'), false);

        $this->assertDatabaseHas('exam_attempts', ['id' => $data['attempt']->id, 'status' => 'submitted', 'passed' => 1]);
        $this->assertDatabaseHas('certificates', ['exam_attempt_id' => $data['attempt']->id, 'status' => 'issued']);
        $this->assertDatabaseCount('certificate_documents', 4);

        $this->get(route('student.certificates'))
            ->assertOk()
            ->assertSee('View 3 certificates');
        $certificate = Certificate::query()->firstOrFail();
        $this->get(route('certificates.show', $certificate))
            ->assertOk()
            ->assertSee('Knowledge Assessment Report')
            ->assertSee('Completion Card - Front')
            ->assertSee('Completion Card - Back')
            ->assertSee($data['student']->display_name);
    }

    public function test_certificate_expiration_uses_the_exam_certificate_validity_years(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 10:00:00'));
        try {
            $data = $this->makeAttempt('submitted', true, certificateValidityYears: 3);

            $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);

            $this->assertNotNull($certificate);
            $this->assertTrue($certificate->issued_at->equalTo(Carbon::parse('2026-04-15 10:00:00')));
            $this->assertTrue($certificate->expires_at->equalTo(Carbon::parse('2029-04-15 10:00:00')));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_certificate_expiration_defaults_to_two_years_when_exam_has_no_validity_configured(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 10:00:00'));
        try {
            $data = $this->makeAttempt('submitted', true, certificateValidityYears: null);

            $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);

            $this->assertNotNull($certificate);
            $this->assertTrue($certificate->expires_at->equalTo(Carbon::parse('2028-04-15 10:00:00')));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_certificate_expiration_correctly_handles_a_leap_day_passing_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2028-02-29 09:00:00'));
        try {
            $data = $this->makeAttempt('submitted', true, certificateValidityYears: 4);

            $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);

            // 2028 and 2032 are both leap years, so a validity of exactly 4 years
            // lands on another Feb 29 rather than drifting to Mar 1 - proof the
            // calculation uses calendar-aware Carbon arithmetic, not a fixed
            // "365 * years" day count that would corrupt this date.
            $this->assertNotNull($certificate);
            $this->assertTrue($certificate->expires_at->equalTo(Carbon::parse('2032-02-29 09:00:00')));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_certificate_expiration_supports_dates_beyond_the_mysql_timestamp_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 13:50:16'));
        try {
            $data = $this->makeAttempt('submitted', true, certificateValidityYears: 20);

            $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);

            $this->assertNotNull($certificate);
            $this->assertTrue($certificate->expires_at->equalTo(Carbon::parse('2046-08-24 13:50:16')));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_changing_an_exams_validity_years_does_not_alter_an_already_issued_certificates_expiration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 10:00:00'));
        try {
            $data = $this->makeAttempt('submitted', true, certificateValidityYears: 3);
            $certificate = app(IssueCertificateAction::class)->execute($data['attempt']);
            $this->assertTrue($certificate->expires_at->equalTo(Carbon::parse('2029-04-15 10:00:00')));

            // The exam's duration changes after the certificate was already issued
            // (e.g. an admin edit). The certificate is a point-in-time snapshot, so
            // re-issuing for the same attempt must keep returning the original
            // certificate untouched rather than recomputing its expiration.
            $data['attempt']->exam->update(['certificate_validity_years' => 5]);

            $reissued = app(IssueCertificateAction::class)->execute($data['attempt']->fresh());

            $this->assertSame($certificate->id, $reissued->id);
            $this->assertTrue($reissued->expires_at->equalTo(Carbon::parse('2029-04-15 10:00:00')));
        } finally {
            Carbon::setTestNow();
        }
    }

    /** @return array{attempt: ExamAttempt, student: User, course: Course, trainingClass: TrainingClass, provider: TrainingProvider} */
    private function makeAttempt(string $status, bool $correct, ?int $certificateValidityYears = null): array
    {
        $this->seedRoles();
        $student = User::factory()->student()->create();
        $provider = TrainingProvider::factory()->create(['name' => 'Cairo Well Control Academy']);
        $course = Course::factory()->create(['name' => 'Drilling Operations']);
        $trainingClass = TrainingClass::factory()->create([
            'course_id' => $course->id,
            'training_provider_id' => $provider->id,
            'status' => 'active',
            'class_number' => 'CLASS-CERT-001',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $group = Group::create(['name' => 'Certificate Test Group', 'status' => 'active']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);
        $exam = Exam::create([
            'course_id' => $course->id,
            'name' => 'Drilling Operations Final Exam',
            'code' => 'CERT-EXAM-001',
            'passing_score' => 50,
            'retake_score' => 40,
            'certificate_validity_years' => $certificateValidityYears,
            'question_order_mode' => 'static',
            'status' => 'published',
        ]);
        $question = Question::create([
            'course_id' => $course->id,
            'question_text' => 'Which control must be verified before drilling operations?',
            'type' => 'mcq',
            'difficulty' => 'easy',
            'default_marks' => 1,
            'correct_answer_text' => null,
            'is_active' => true,
        ]);
        $correctOption = QuestionOption::create(['question_id' => $question->id, 'option_text' => 'The approved barrier controls', 'is_correct' => true, 'display_order' => 1]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'An unrelated report', 'is_correct' => false, 'display_order' => 2]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'display_order' => 1, 'points' => 1]);
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
            'status' => $status,
            'started_at' => now()->subMinutes(20),
            'expires_at' => now()->addHour(),
            'submitted_at' => $status === 'submitted' ? now()->subMinutes(2) : null,
        ]);
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'display_order' => 1,
            'points' => 1,
            'answer' => $correct ? $correctOption->public_id : 'invalid-answer',
            'answered_at' => now()->subMinutes(5),
        ]);

        return compact('attempt', 'student', 'course', 'trainingClass', 'provider');
    }
}
