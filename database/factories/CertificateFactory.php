<?php

namespace Database\Factories;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 *
 * Certificate is a denormalized snapshot (see migration
 * 2026_08_10_000002_create_certificates_table.php) - the student/exam/etc.
 * name fields are plain strings frozen at issuance, not derived live from
 * the related records, so the factory fills them independently. The FK ids
 * (student_user_id/exam_id/exam_schedule_id) do derive from the created
 * attempt though, so the certificate stays internally consistent with it.
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        $issuedAt = now();
        $studentName = fake()->name();

        return [
            'certificate_number' => $this->certificateNumber(),
            'exam_attempt_id' => ExamAttempt::factory()->submitted(),
            'student_user_id' => fn (array $attributes): int => ExamAttempt::query()->whereKey($attributes['exam_attempt_id'])->value('student_user_id'),
            'exam_id' => fn (array $attributes): int => ExamAttempt::query()->whereKey($attributes['exam_attempt_id'])->value('exam_id'),
            'exam_schedule_id' => fn (array $attributes): int => ExamAttempt::query()->whereKey($attributes['exam_attempt_id'])->value('exam_schedule_id'),
            'training_class_id' => null,
            'training_provider_id' => null,
            'instructor_user_id' => null,
            'student_name' => $studentName,
            'student_email' => fake()->safeEmail(),
            'student_wellsharp_id' => Str::upper(Str::random(6)),
            'exam_name' => ucfirst(fake()->words(4, true)).' Exam',
            'exam_code' => Str::upper(Str::random(6)),
            'subject_name' => ucfirst(fake()->words(3, true)),
            'class_number' => null,
            'group_name' => null,
            'provider_name' => null,
            'instructor_name' => null,
            'score' => 85,
            'passing_score' => 75,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addYears(2),
            'status' => CertificateStatus::Issued,
            'revoked_at' => null,
            'revocation_reason' => null,
            'pdf_path' => null,
            'issued_by_user_id' => null,
        ];
    }

    public function revoked(string $reason = 'Factory-created revoked certificate for testing.'): static
    {
        return $this->state(fn (): array => [
            'status' => CertificateStatus::Revoked,
            'revoked_at' => now()->subDay(),
            'revocation_reason' => $reason,
        ]);
    }

    private function certificateNumber(): string
    {
        return 'WS-CERT-'.now()->format('Y').'-'.Str::upper(Str::random(10));
    }
}
