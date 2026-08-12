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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueCertificateAction
{
    public function __construct(
        private readonly ExamScoringService $scoring,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(ExamAttempt $attempt): ?Certificate
    {
        return DB::transaction(function () use ($attempt): ?Certificate {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            if ($attempt->status !== ExamAttemptStatus::Submitted) {
                return null;
            }

            $result = $this->scoring->calculate($attempt);
            $attempt->update(['score' => $result['score'], 'passed' => $result['passed'], 'scored_at' => $attempt->scored_at ?: now()]);

            if (! $result['passed']) {
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
                'schedule.trainingClass.provider',
                'schedule.trainingClass.provider',
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
                'training_provider_id' => $trainingClass?->training_provider_id,
                'instructor_user_id' => null,
                'issued_by_user_id' => auth()->id(),
                'student_name' => $attempt->student?->display_name ?: $attempt->student?->wellsharp_id,
                'student_email' => $attempt->student?->email,
                'student_wellsharp_id' => $attempt->student?->wellsharp_id,
                'exam_name' => $attempt->exam?->name,
                'exam_code' => $attempt->exam?->code,
                'subject_name' => $attempt->exam?->subject?->name,
                'class_number' => $trainingClass?->class_number,
                'group_name' => $attempt->schedule?->group?->name,
                'provider_name' => $trainingClass?->provider?->name,
                'instructor_name' => null,
                'score' => $result['score'],
                'passing_score' => $attempt->exam?->passing_score ?? 0,
                'issued_at' => $issuedAt,
                'expires_at' => $issuedAt->copy()->addYears(2),
                'status' => CertificateStatus::Issued,
            ]);
            $this->ensureDocuments($certificate);

            $this->audit->record('certificate.issued', $certificate, null, $certificate->toArray());

            return $certificate;
        });
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
