<?php

namespace Database\Factories;

use App\Enums\CertificateDocumentType;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateDocument>
 *
 * `type` defaults to KnowledgeAssessmentReport (single-row-safe, per the
 * unique(certificate_id, type) constraint). Attaching all 3 document types
 * for one certificate requires 3 explicit creates/->sequence(...).
 */
class CertificateDocumentFactory extends Factory
{
    protected $model = CertificateDocument::class;

    public function definition(): array
    {
        return [
            'certificate_id' => Certificate::factory(),
            'type' => CertificateDocumentType::KnowledgeAssessmentReport,
            'title' => 'Knowledge Assessment Report',
            'path' => null,
            'issued_at' => now(),
        ];
    }

    public function completionCardFront(): static
    {
        return $this->state(fn (): array => ['type' => CertificateDocumentType::CompletionCardFront, 'title' => 'Completion Card (Front)']);
    }

    public function completionCardBack(): static
    {
        return $this->state(fn (): array => ['type' => CertificateDocumentType::CompletionCardBack, 'title' => 'Completion Card (Back)']);
    }
}
