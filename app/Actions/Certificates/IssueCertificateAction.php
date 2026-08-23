<?php

namespace App\Actions\Certificates;

use App\Enums\CertificateDocumentType;
use App\Enums\CertificateStatus;
use App\Enums\ExamAttemptStatus;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\ExamAttempt;
use App\Services\AuditRecorder;
use App\Services\EffectiveScoreService;
use App\Services\ExamScoringService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueCertificateAction
{
    public function __construct(
        private readonly ExamScoringService $scoring,
        private readonly EffectiveScoreService $effectiveScore,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(ExamAttempt $attempt): ?Certificate
    {
        return DB::transaction(function () use ($attempt): ?Certificate {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            if ($attempt->status !== ExamAttemptStatus::Submitted) {
                return null;
            }

            // The raw automated Knowledge Exam result is always recalculated and
            // persisted here exactly as before (BR-027) - a Skills Score override
            // never touches exam_attempts.score/passed, only which number counts
            // for certification purposes, decided below via the effective score.
            $result = $this->scoring->calculate($attempt);
            $attempt->update(['score' => $result['score'], 'passed' => $result['passed'], 'scored_at' => $attempt->scored_at ?: now()]);

            $effective = $this->effectiveScore->forAttempt($attempt);

            if (! $effective['passed']) {
                // Covers both "never passed" and "was overridden from passing to
                // failing after an earlier issuance": either way, an existing
                // Certificate row is left completely untouched - certificates are
                // immutable snapshots once issued (BR-031), and this domain has no
                // implemented revocation workflow (BR-033) to safely retract one.
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
                'training_provider_id' => $trainingClass?->training_provider_id,
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
                'provider_name' => $trainingClass?->provider?->name,
                'instructor_name' => $trainingClass?->instructor?->display_name,
                // The certificate records the *effective* score - the one that
                // actually cleared the passing threshold - so the printed
                // "score / passing score" pair is always internally consistent.
                // The original Knowledge Exam result is never lost: it stays on
                // exam_attempts.score, and enrollments.skills_score keeps the
                // override, if any - both untouched by this write.
                'score' => $effective['score'],
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
