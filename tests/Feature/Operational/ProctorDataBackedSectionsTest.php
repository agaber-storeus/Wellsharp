<?php

namespace Tests\Feature\Operational;

use App\Enums\CertificateDocumentType;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\ClassStaffAssignment;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProctorDataBackedSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_proctor_profile_can_be_read_and_updated(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        $proctor = User::factory()->proctor()->create();

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.profile'))
            ->assertOk()
            ->assertSee($proctor->profile->first_name)
            ->assertSee('Update Your Profile')
            ->assertSee('Company')
            ->assertSee('Change Password')
            ->assertSee('New Password');

        $response = $this->patch(route('proctor.profile.update'), [
            'first_name' => 'Updated',
            'last_name' => 'Proctor',
            'email' => 'updated-proctor@example.test',
            'birthday' => '1985-02-03',
            'phone' => '+201000000000',
            'address' => 'Cairo Training Center',
            'country' => 'Egypt',
            'state' => 'Cairo',
            'city' => 'Cairo',
            'postal_code' => '11511',
            'company' => 'WellSharp Testing',
            'position' => 'Senior Proctor',
            'employee_id' => 'EMP-100',
            'password' => 'Updatd12',
            'password_confirmation' => 'Updatd12',
            'profile_photo' => UploadedFile::fake()->image('proctor.png'),
        ])->assertRedirect();

        $proctor->refresh();
        $response->assertSessionHas('auth.session_version', $proctor->session_version);
        $this->get(route('proctor.profile'))->assertOk();
        $this->assertSame('updated-proctor@example.test', $proctor->email);
        $this->assertTrue(Hash::check('Updatd12', $proctor->password));
        $profile = $proctor->profile()->firstOrFail();
        $this->assertSame('Cairo', $profile->city);
        $this->assertSame('Senior Proctor', $profile->position);
        $this->assertNotNull($profile->profile_photo_path);
        $this->assertStringEndsWith('.webp', $profile->profile_photo_path);
        Storage::disk('public')->assertExists($profile->profile_photo_path);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.profile_updated']);
    }

    public function test_certificate_lookup_returns_certificates_for_any_class_and_supports_filters(): void
    {
        $this->seedRoles();
        $proctor = User::factory()->proctor()->create();
        $student = User::factory()->student()->create();
        $provider = TrainingProvider::factory()->create(['name' => 'Assigned Provider']);
        $course = Course::factory()->create(['name' => 'Assigned Subject']);
        $class = TrainingClass::factory()->create(['course_id' => $course->id, 'training_provider_id' => $provider->id, 'class_number' => 'CERT-001']);
        ClassStaffAssignment::create(['class_id' => $class->id, 'user_id' => $proctor->id, 'assignment_role' => 'proctor', 'status' => 'active', 'assigned_at' => now()]);
        $group = Group::create(['name' => 'Certificate Group', 'status' => 'active']);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Assigned Exam', 'passing_score' => 70, 'retake_score' => 60, 'question_order_mode' => 'static', 'status' => 'published']);
        $schedule = ExamSchedule::create(['exam_id' => $exam->id, 'group_id' => $group->id, 'training_class_id' => $class->id, 'start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->subDay()->toDateString(), 'duration_minutes' => 60, 'status' => 'completed']);
        $attempt = ExamAttempt::create(['exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $student->id, 'attempt_number' => 1, 'status' => 'submitted', 'started_at' => now()->subDays(2), 'submitted_at' => now()->subDay(), 'score' => 88, 'passed' => true]);
        Certificate::create([
            'certificate_number' => 'WS-CERT-TEST-001', 'exam_attempt_id' => $attempt->id, 'student_user_id' => $student->id,
            'exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'training_class_id' => $class->id,
            'training_provider_id' => $provider->id, 'student_name' => 'Certificate Student', 'student_email' => $student->email,
            'student_wellsharp_id' => $student->wellsharp_id, 'exam_name' => $exam->name, 'subject_name' => $course->name,
            'class_number' => $class->class_number, 'provider_name' => $provider->name, 'score' => 88, 'passing_score' => 70,
            'issued_at' => now()->subDay(), 'status' => 'issued',
        ]);

        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.certificate', ['certificate_id' => 'WS-CERT-TEST-001']))
            ->assertOk()
            ->assertSee('WS-CERT-TEST-001')
            ->assertSee('Certificate Student')
            ->assertSee('Assigned Provider');

        $this->get(route('proctor.dashboard'))
            ->assertSee($class->public_id)
            ->assertDontSee('1C52A6B5');

        $this->get(route('proctor.certificate', ['certificate_id' => 'DOES-NOT-EXIST']))
            ->assertOk()
            ->assertSee('No certificate records match the selected search.');
    }

    public function test_certificate_options_column_preview_renders_the_real_branded_certificate_pdf(): void
    {
        $this->seedRoles();
        $proctor = User::factory()->proctor()->create();
        $student = User::factory()->student()->create();
        $provider = TrainingProvider::factory()->create(['name' => 'Assigned Provider']);
        $course = Course::factory()->create(['name' => 'Assigned Subject']);
        $class = TrainingClass::factory()->create(['course_id' => $course->id, 'training_provider_id' => $provider->id, 'class_number' => 'CERT-002']);
        ClassStaffAssignment::create(['class_id' => $class->id, 'user_id' => $proctor->id, 'assignment_role' => 'proctor', 'status' => 'active', 'assigned_at' => now()]);
        $group = Group::create(['name' => 'Certificate Group 2', 'status' => 'active']);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Assigned Exam', 'passing_score' => 70, 'retake_score' => 60, 'question_order_mode' => 'static', 'status' => 'published']);
        $schedule = ExamSchedule::create(['exam_id' => $exam->id, 'group_id' => $group->id, 'training_class_id' => $class->id, 'start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->subDay()->toDateString(), 'duration_minutes' => 60, 'status' => 'completed']);
        $attempt = ExamAttempt::create(['exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'student_user_id' => $student->id, 'attempt_number' => 1, 'status' => 'submitted', 'started_at' => now()->subDays(2), 'submitted_at' => now()->subDay(), 'score' => 92, 'passed' => true]);
        $certificate = Certificate::create([
            'certificate_number' => 'WS-CERT-TEST-002', 'exam_attempt_id' => $attempt->id, 'student_user_id' => $student->id,
            'exam_id' => $exam->id, 'exam_schedule_id' => $schedule->id, 'training_class_id' => $class->id,
            'training_provider_id' => $provider->id, 'student_name' => 'Options Column Student', 'student_email' => $student->email,
            'student_wellsharp_id' => $student->wellsharp_id, 'exam_name' => $exam->name, 'subject_name' => $course->name,
            'class_number' => $class->class_number, 'provider_name' => $provider->name, 'score' => 92, 'passing_score' => 70,
            'issued_at' => now()->subDay(), 'status' => 'issued',
        ]);
        $front = CertificateDocument::create(['certificate_id' => $certificate->id, 'type' => CertificateDocumentType::CompletionCardFront, 'title' => 'Completion Card - Front', 'issued_at' => $certificate->issued_at]);
        CertificateDocument::create(['certificate_id' => $certificate->id, 'type' => CertificateDocumentType::CompletionCardBack, 'title' => 'Completion Card - Back', 'issued_at' => $certificate->issued_at]);
        CertificateDocument::create(['certificate_id' => $certificate->id, 'type' => CertificateDocumentType::KnowledgeAssessmentReport, 'title' => 'Knowledge Assessment Report', 'issued_at' => $certificate->issued_at]);

        $session = $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version]);

        // The page itself: no "Clear" button, and the trainee name is no longer a details link.
        $session->get(route('proctor.certificate'))
            ->assertOk()
            ->assertDontSee('Clear')
            ->assertDontSee('certificate.show_url', false);

        // The Options-column payload: preview/download point at the real completion card document, not the report or the details bundle.
        $row = collect($session->getJson(route('proctor.certificate.data'))->assertOk()->json('data'))
            ->firstWhere('certificate_number', 'WS-CERT-TEST-002');
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('show_url', $row);
        $this->assertSame(route('certificates.documents.preview', [$certificate, $front]), $row['preview_url']);
        $this->assertSame(route('certificates.documents.download', [$certificate, $front]), $row['download_url']);

        // Preview streams the same branded Certificate of Completion + wallet card PDF as the download, just inline instead of as an attachment.
        $preview = $session->get($row['preview_url'])->assertOk();
        $preview->assertHeader('Content-Type', 'application/pdf');
        $preview->assertHeader('Content-Disposition', 'inline; filename="'.str($certificate->student_name.'-'.$front->title)->slug('-').'.pdf"');
        $this->assertStringStartsWith('%PDF-', $preview->getContent());

        // The PDF's own /Title metadata carries the course name, so the browser's inline PDF viewer
        // shows a real, per-student document title instead of the URL's generic "preview" segment.
        $this->assertSame(1, preg_match('/\/Title\s*\((.*?)\)/s', $preview->getContent(), $titleMatch));
        $decodedTitle = str_starts_with($titleMatch[1], "\xfe\xff")
            ? mb_convert_encoding(substr($titleMatch[1], 2), 'UTF-8', 'UTF-16BE')
            : $titleMatch[1];
        $this->assertSame($course->name, $decodedTitle);

        $download = $session->get($row['download_url'])->assertOk();
        $download->assertHeader('Content-Type', 'application/pdf');
        $download->assertHeader('Content-Disposition', 'attachment; filename="'.str($certificate->student_name.'-'.$front->title)->slug('-').'.pdf"');
        $this->assertStringStartsWith('%PDF-', $download->getContent());
    }
}
