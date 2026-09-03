<?php

namespace App\Enums;

enum CertificateDocumentType: string
{
    case FullCertificate = 'full_certificate';
    case KnowledgeAssessmentReport = 'knowledge_assessment_report';
    case CompletionCardFront = 'completion_card_front';
    case CompletionCardBack = 'completion_card_back';

    public function label(): string
    {
        return match ($this) {
            self::FullCertificate => 'Full Certificate',
            self::KnowledgeAssessmentReport => 'Knowledge Assessment Report',
            self::CompletionCardFront => 'Completion Card - Front',
            self::CompletionCardBack => 'Completion Card - Back',
        };
    }
}
