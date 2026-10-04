<?php

namespace App\Actions\Certificates;

use App\Enums\CertificateDocumentType;
use App\Enums\CertificateStatus;
use App\Enums\ExamAttemptStatus;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\ExamAttempt;
use App\Services\AuditRecorder;
use App\Services\ExamScoringService;
use App\Services\KnowledgeResultService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueCertificateAction
{
    public function __construct(
        private readonly ExamScoringService $scoring,
        private readonly KnowledgeResultService $knowledgeResults,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(ExamAttempt $attempt): ?Certificate
    {
        return DB::transaction(function () use ($attempt): ?Certificate {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            if ($attempt->status !== ExamAttemptStatus::Submitted) {
                return null;
            }

            if ($attempt->score === null) {
                $calculated = $this->scoring->calculate($attempt);
                $attempt->update(['score' => $calculated['score'], 'passed' => $calculated['passed'], 'scored_at' => $attempt->scored_at ?: now()]);
            }
            $result = $this->knowledgeResults->resolve($attempt->fresh());

            if (! $result['certificate_eligible']) {
                return null;
            }

            $existing = Certificate::query()->where('exam_attempt_id', $attempt->getKey())->first();
            if ($existing) {
                $this->ensureDocuments($existing);

                return $existing;
            }

            $attempt->loadMissing([
                'student.profile',
                'exam.subject',
                'schedule.group',
                'schedule.provider',
                'schedule.trainingClass.provider',
                'schedule.trainingClass.instructor',
            ]);
            $trainingClass = $attempt->schedule?->trainingClass;
            $issuedAt = now();
            $certificate = Certificate::create([
                'certificate_number' => $this->certificateNumber(),
                'exam_attempt_id' => $attempt->getKey(),
                'student_user_id' => $attempt->student_user_id,
                'exam_id' => $attempt->exam_id,
                'exam_schedule_id' => $attempt->exam_schedule_id,
                'training_class_id' => $trainingClass?->getKey(),
                'training_provider_id' => $attempt->schedule?->training_provider_id ?: $trainingClass?->training_provider_id,
                'instructor_user_id' => $trainingClass?->instructor_id,
                'issued_by_user_id' => auth()->id(),
                'student_name' => $attempt->student?->display_name ?: $attempt->student?->wellsharp_id,
                'student_email' => $attempt->student?->email,
                'student_wellsharp_id' => $attempt->student?->wellsharp_id,
                'exam_name' => $attempt->exam?->name,
                'exam_code' => $attempt->exam?->code,
                'subject_name' => $attempt->exam?->subject?->name,
                'class_number' => $trainingClass?->class_number,
                'group_name' => $attempt->schedule?->group?->name,
                'provider_name' => $attempt->schedule?->provider?->name ?: $trainingClass?->provider?->name,
                'instructor_name' => $trainingClass?->instructor?->display_name,
                'score' => $result['final_knowledge_score'],
                'passing_score' => $attempt->exam?->passing_score ?? 0,
                'issued_at' => $issuedAt,
                'expires_at' => $this->expirationDate($issuedAt, $attempt->exam?->certificate_validity_years),
                'status' => CertificateStatus::Issued,
            ]);
            $this->ensureDocuments($certificate);

            $this->audit->record('certificate.issued', $certificate, null, $certificate->toArray());

            return $certificate;
        });
    }

    /**
     * A certificate's validity duration is fixed to whatever the exam's
     * `certificate_validity_years` was at the moment of issuance; later
     * changes to the exam never retroactively alter an already-issued
     * certificate's `expires_at`, since that value is only ever written here.
     * Exams with no configured duration keep the project's prior default of
     * 2 years, matching legacy behavior for exams created before this field.
     */
    private function expirationDate(Carbon $issuedAt, ?int $validityYears): Carbon
    {
        return $issuedAt->copy()->addYears($validityYears ?? 2);
    }

    private function certificateNumber(): string
    {
        do {
            $number = 'WS-CERT-'.now()->format('Y').'-'.Str::upper(Str::random(10));
        } while (Certificate::query()->where('certificate_number', $number)->exists());

        return $number;
    }

    private function ensureDocuments(Certificate $certificate): void
    {
        foreach (CertificateDocumentType::cases() as $type) {
            CertificateDocument::query()->firstOrCreate(
                ['certificate_id' => $certificate->getKey(), 'type' => $type],
                [
                    'title' => $type->label(),
                    'issued_at' => $certificate->issued_at ?: now(),
                ],
            );
        }
    }
}
