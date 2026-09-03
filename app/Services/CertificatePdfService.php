<?php

namespace App\Services;

use App\Enums\CertificateDocumentType;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use setasign\Fpdi\Fpdi;

/**
 * Stamps real certificate data onto the official IADC WellSharp certificate
 * template (resources/pdf-templates/certificate-of-completion.pdf) using
 * FPDI: the template's own pages (banner artwork, logo, labels, underlines,
 * wallet-card layout) are imported as-is and reused unchanged for every
 * certificate, and only the per-student values are drawn on top at the same
 * coordinates the template's own sample values occupy. This is the same
 * physical document trainees receive, not a recreation of it.
 */
class CertificatePdfService
{
    public function __construct(private readonly CertificateQrCodeService $qrCodes) {}

    private const TEMPLATE_PATH = 'pdf-templates/certificate-of-completion.pdf';

    private const PAGE1_HEIGHT_PT = 792.0;

    private const PAGE2_HEIGHT_PT = 841.89;

    private const FONT = 'Helvetica';

    public function render(CertificateDocument $document): string
    {
        $certificate = $document->certificate()->with([
            'exam.subject.level',
            'exam.subject.stacks',
            'exam.subject.supplements',
            'trainingClass.provider',
        ])->firstOrFail();

        if ($document->type === CertificateDocumentType::FullCertificate) {
            return $this->renderFullCertificate($certificate);
        }

        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        if ($document->type === CertificateDocumentType::KnowledgeAssessmentReport) {
            $pdf->SetTitle('Knowledge Assessment Report');
            $this->renderReportPage($pdf, $certificate);

            return $pdf->Output('S');
        }

        $data = $this->fieldValues($certificate);
        $templatePath = resource_path(self::TEMPLATE_PATH);
        $pdf->SetTitle($data['courseName']);
        $pdf->setSourceFile($templatePath);

        $this->stampPage($pdf, 1, self::PAGE1_HEIGHT_PT, $this->page1Fields($data));
        $this->clearTemplateQrCodes($pdf, 1);
        $this->stampPage($pdf, 2, self::PAGE2_HEIGHT_PT, $this->page2Fields($data));
        $this->clearTemplateQrCodes($pdf, 2);
        if ($document->type === CertificateDocumentType::CompletionCardBack) {
            $this->stampQrCode($pdf, $certificate);
        }

        return $pdf->Output('S');
    }

    private function renderFullCertificate(Certificate $certificate): string
    {
        $data = $this->fieldValues($certificate);
        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetTitle('Full Certificate - '.$data['traineeName']);
        $pdf->setSourceFile(resource_path(self::TEMPLATE_PATH));

        // Full Certificate contains both dynamic certificate pages. Its back
        // page intentionally has no QR; the QR belongs only to the separate
        // Completion Card - Back document.
        $this->stampPage($pdf, 1, self::PAGE1_HEIGHT_PT, $this->page1Fields($data));
        $this->clearTemplateQrCodes($pdf, 1);
        $this->renderFullCertificateBackPage($pdf, $data);

        return $pdf->Output('S');
    }

    private function renderFullCertificateBackPage(Fpdi $pdf, array $data): void
    {
        $pdf->AddPage('P', [612, 792]);
        $pdf->SetFillColor(247, 248, 250);
        $pdf->Rect(0, 0, 612, 792, 'F');
        $pdf->SetDrawColor(205, 210, 216);
        $pdf->SetLineWidth(1);
        $pdf->Rect(56, 190, 500, 330, 'D');

        $logo = public_path('images/iadcLoginLgo.png');
        if (is_file($logo)) {
            $pdf->Image($logo, 80, 220, 150);
        }

        $pdf->SetFont(self::FONT, 'B', 11);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->Text(80, 305, 'COMPLETION CARD - BACK');
        $pdf->SetFont(self::FONT, '', 11);
        $pdf->SetTextColor(55, 65, 81);
        $pdf->SetXY(80, 330);
        $pdf->MultiCell(310, 17, 'This individual has successfully completed a well control course at an institution accredited by the International Association of Drilling Contractors.');
        $pdf->SetXY(80, 390);
        $pdf->MultiCell(310, 17, 'For scheduling training or replacement of a lost card, please call the training provider with the information provided on this completion card.');
        $pdf->SetFont(self::FONT, 'B', 10);
        $pdf->Text(80, 475, 'Certificate Number: '.$data['certificateNumber']);
    }

    private function stampQrCode(Fpdi $pdf, Certificate $certificate): void
    {
        $this->stampQrCodeAt($pdf, $certificate, 235, 242, 70);
    }

    private function clearTemplateQrCodes(Fpdi $pdf, int $page): void
    {
        // The imported artwork contains sample QR images. Remove them from
        // every generated document so a generic external QR can never remain.
        $pdf->SetFillColor(255, 255, 255);
        if ($page === 1) {
            $pdf->Rect(42, 560, 85, 85, 'F');
        } else {
            $pdf->Rect(42, 18, 105, 105, 'F');
            $pdf->Rect(215, 220, 105, 105, 'F');
        }
    }

    private function stampQrCodeAt(Fpdi $pdf, Certificate $certificate, float $x, float $y, float $size): void
    {
        $qr = $this->qrCodes->png($certificate, 220);
        $path = tempnam(sys_get_temp_dir(), 'certificate-qr-');

        if ($path === false) {
            return;
        }

        try {
            file_put_contents($path, $qr);
            $pdf->Image($path, $x, $y, $size, $size, 'PNG');
        } finally {
            @unlink($path);
        }
    }

    /**
     * The Knowledge Assessment Report has no counterpart page in the official
     * completion-card template (that template only covers the wallet card
     * front/back), so it's drawn as its own plain page rather than stamped
     * onto imported template artwork.
     */
    private function renderReportPage(Fpdi $pdf, Certificate $certificate): void
    {
        $pdf->AddPage('P', [612, 792]);

        $pdf->SetFillColor(13, 41, 64);
        $pdf->Rect(0, 0, 612, 112, 'F');
        $pdf->SetFillColor(214, 10, 31);
        $pdf->Rect(0, 112, 612, 6, 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont(self::FONT, 'B', 17);
        $pdf->Text(44, 63, 'IADC WELLSHARP');
        $pdf->SetFont(self::FONT, 'B', 22);
        $pdf->Text(44, 97, 'Knowledge Assessment Report');

        $rows = [
            ['Trainee name', $certificate->student_name],
            ['Assessment', $certificate->exam_name],
            ['Subject', $certificate->subject_name],
            ['Class', $certificate->class_number ?: 'Not configured'],
            ['Provider', $certificate->provider_name ?: $certificate->trainingClass?->provider?->name ?: 'Not configured'],
            ['Instructor', $certificate->instructor_name ?: 'Not configured'],
            ['Assessment date', $certificate->issued_at?->format('F j, Y g:i A') ?: 'Not configured'],
            ['Score', $certificate->score !== null ? number_format((float) $certificate->score, 2).'%' : 'Not configured'],
            ['Passing score', $certificate->passing_score !== null ? $certificate->passing_score.'%' : 'Not configured'],
            ['Certificate number', $certificate->certificate_number],
            ['Valid through', $certificate->expires_at?->format('F j, Y') ?: 'Not configured'],
        ];

        $y = 158;
        foreach ($rows as [$label, $value]) {
            $pdf->SetFont(self::FONT, '', 8);
            $pdf->SetTextColor(92, 117, 138);
            $pdf->Text(48, $y, strtoupper((string) $label));
            $pdf->SetFont(self::FONT, '', 13);
            $pdf->SetTextColor(31, 41, 55);
            $pdf->Text(48, $y + 20, (string) $value);
            $y += 42;
        }

        $pdf->SetFont(self::FONT, '', 10);
        $pdf->SetTextColor(75, 85, 99);
        $footerY = $y + 12;
        foreach ([
            'This trainee successfully completed the IADC Well Control Knowledge Assessment.',
            'The assessment was completed for '.$certificate->subject_name.'. Keep this report with the Course Completion Card.',
        ] as $paragraph) {
            $pdf->SetXY(44, $footerY);
            $pdf->MultiCell(524, 14, $paragraph);
            $footerY += 30;
        }
    }

    /** @param array<int, array{y: float, fontSize: float, align: string, text: string, box: array{0: float, 1: float}}> $fields */
    private function stampPage(Fpdi $pdf, int $pageNumber, float $pageHeightPt, array $fields): void
    {
        $templateId = $pdf->importPage($pageNumber);
        $size = $pdf->getTemplateSize($templateId);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId);

        foreach ($fields as $field) {
            $this->stampField($pdf, $pageHeightPt, $field);
        }
    }

    /** @param array{y: float, fontSize: float, align: string, text: string, box: array{0: float, 1: float}} $field */
    private function stampField(Fpdi $pdf, float $pageHeightPt, array $field): void
    {
        [$boxLeft, $boxRight] = $field['box'];
        $fontSize = $this->fitFontSize($pdf, $field['text'], $field['fontSize'], $boxRight - $boxLeft);

        // Mask the template's own sample value with white before stamping the real one,
        // covering from just above the underline to comfortably above the text ascent.
        // The mask itself is padded slightly beyond the alignment box to avoid a visible
        // hairline seam at its edge, without changing where text is actually positioned.
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($boxLeft - 1.5, $pageHeightPt - $field['y'] - $field['fontSize'] - 3, ($boxRight - $boxLeft) + 3, $field['fontSize'] + 6, 'F');

        $pdf->SetFont(self::FONT, '', $fontSize);
        $pdf->SetTextColor(31, 41, 55);

        $textWidth = $pdf->GetStringWidth($field['text']);
        $x = match ($field['align']) {
            'C' => $boxLeft + (($boxRight - $boxLeft) - $textWidth) / 2,
            'R' => $boxRight - $textWidth,
            default => $boxLeft,
        };

        $pdf->Text($x, $pageHeightPt - $field['y'], $field['text']);
    }

    /** Shrinks the font until the text fits the box, matching the template's single-line fields. */
    private function fitFontSize(Fpdi $pdf, string $text, float $fontSize, float $maxWidth): float
    {
        $pdf->SetFont(self::FONT, '', $fontSize);
        while ($fontSize > 6.0 && $pdf->GetStringWidth($text) > $maxWidth) {
            $fontSize -= 0.5;
            $pdf->SetFont(self::FONT, '', $fontSize);
        }

        return $fontSize;
    }

    /** @return array<int, array{y: float, fontSize: float, align: string, text: string, box: array{0: float, 1: float}}> */
    private function page1Fields(array $data): array
    {
        return [
            ['y' => 466.02, 'fontSize' => 16, 'align' => 'C', 'text' => $data['traineeName'], 'box' => [56.69, 555.31]],
            ['y' => 416.41, 'fontSize' => 12, 'align' => 'L', 'text' => $data['courseName'], 'box' => [65.20, 555.31]],
            ['y' => 368.22, 'fontSize' => 12, 'align' => 'L', 'text' => $data['supplementName'], 'box' => [65.20, 555.31]],
            ['y' => 318.61, 'fontSize' => 12, 'align' => 'L', 'text' => $data['completionDate'], 'box' => [65.20, 300.00]],
            ['y' => 318.61, 'fontSize' => 12, 'align' => 'L', 'text' => $data['expirationDate'], 'box' => [314.65, 555.31]],
            ['y' => 270.43, 'fontSize' => 12, 'align' => 'L', 'text' => $data['providerName'], 'box' => [65.20, 300.00]],
            ['y' => 270.43, 'fontSize' => 12, 'align' => 'L', 'text' => $data['providerNumber'], 'box' => [314.65, 555.31]],
            ['y' => 222.24, 'fontSize' => 12, 'align' => 'L', 'text' => $data['providerPhone'], 'box' => [65.20, 555.31]],
            ['y' => 174.05, 'fontSize' => 12, 'align' => 'L', 'text' => $data['instructorName'], 'box' => [65.20, 555.31]],
            ['y' => 74.83, 'fontSize' => 10, 'align' => 'R', 'text' => $data['certificateNumber'], 'box' => [476.00, 566.00]],
        ];
    }

    /** @return array<int, array{y: float, fontSize: float, align: string, text: string, box: array{0: float, 1: float}}> */
    private function page2Fields(array $data): array
    {
        return [
            ['y' => 740.41, 'fontSize' => 8, 'align' => 'L', 'text' => $data['traineeName'], 'box' => [365.67, 565.00]],
            ['y' => 728.50, 'fontSize' => 8, 'align' => 'L', 'text' => $data['courseName'], 'box' => [362.83, 565.00]],
            ['y' => 716.88, 'fontSize' => 7, 'align' => 'L', 'text' => $data['supplementName'], 'box' => [379.84, 565.00]],
            ['y' => 704.41, 'fontSize' => 7, 'align' => 'L', 'text' => $data['completionDate'], 'box' => [374.17, 428.00]],
            ['y' => 704.41, 'fontSize' => 7, 'align' => 'L', 'text' => $data['expirationDate'], 'box' => [487.56, 565.00]],
            ['y' => 691.65, 'fontSize' => 8, 'align' => 'L', 'text' => $data['providerName'], 'box' => [345.83, 565.00]],
            ['y' => 678.90, 'fontSize' => 8, 'align' => 'L', 'text' => $data['providerNumber'], 'box' => [354.33, 415.00]],
            ['y' => 678.90, 'fontSize' => 8, 'align' => 'L', 'text' => $data['providerPhone'], 'box' => [459.21, 565.00]],
            ['y' => 666.14, 'fontSize' => 8, 'align' => 'L', 'text' => $data['instructorName'], 'box' => [374.17, 565.00]],
            ['y' => 651.97, 'fontSize' => 8, 'align' => 'R', 'text' => $data['certificateNumber'], 'box' => [459.00, 565.00]],
        ];
    }

    /** @return array<string, string> */
    private function fieldValues(Certificate $certificate): array
    {
        $subject = $certificate->exam?->subject;
        $courseName = collect([$certificate->subject_name, $subject?->level?->name, $subject?->stacks?->pluck('name')->join(', ')])
            ->filter()
            ->join(', ');
        $provider = $certificate->trainingClass?->provider;

        return [
            'traineeName' => $certificate->student_name,
            'courseName' => $courseName ?: $certificate->exam_name,
            'supplementName' => $subject?->supplements?->pluck('name')->join(', ') ?: '',
            'completionDate' => $certificate->issued_at?->format('j F Y') ?: 'Not configured',
            'expirationDate' => ($certificate->expires_at ?: $certificate->issued_at?->copy()->addYears(2))?->format('j F Y') ?: 'Not configured',
            'providerName' => $certificate->provider_name ?: $provider?->name ?: 'Not configured',
            'providerNumber' => $provider?->provider_number ?: 'Not configured',
            'providerPhone' => $provider?->phone ?: 'Not configured',
            'instructorName' => $certificate->instructor_name ?: 'Not configured',
            'certificateNumber' => $certificate->certificate_number,
        ];
    }
}
