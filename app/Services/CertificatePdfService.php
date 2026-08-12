<?php

namespace App\Services;

use App\Enums\CertificateDocumentType;
use App\Models\Certificate;
use App\Models\CertificateDocument;

/**
 * Builds the small, print-ready certificate documents without an external PDF
 * package. The browser view remains the authoritative interactive version;
 * this service provides a dependable downloadable PDF for each document.
 */
class CertificatePdfService
{
    public function render(CertificateDocument $document): string
    {
        $certificate = $document->certificate()->with([
            'exam.subject.level',
            'exam.subject.stacks',
            'exam.subject.supplements',
            'trainingClass.provider',
        ])->firstOrFail();

        return match ($document->type) {
            CertificateDocumentType::KnowledgeAssessmentReport => $this->renderReport($certificate),
            CertificateDocumentType::CompletionCardFront => $this->renderCompletionCard($certificate, false),
            CertificateDocumentType::CompletionCardBack => $this->renderCompletionCard($certificate, true),
        };
    }

    private function renderReport(Certificate $certificate): string
    {
        return $this->pdfPage(
            'Knowledge Assessment Report',
            [
                ['Trainee name', $certificate->student_name],
                ['Assessment', $certificate->exam_name],
                ['Subject', $certificate->subject_name],
                ['Class', $certificate->class_number ?: 'Not configured'],
                ['Provider', $certificate->provider_name ?: $certificate->trainingClass?->provider?->name ?: 'Not configured'],
                ['Instructor', $certificate->instructor_name ?: 'Not configured'],
                ['Assessment date', $this->date($certificate->issued_at, 'F j, Y g:i A')],
                ['Score', number_format((float) $certificate->score, 2).'%'],
                ['Passing score', $certificate->passing_score.'%'],
                ['Certificate number', $certificate->certificate_number],
                ['Valid through', $this->date($certificate->expires_at, 'F j, Y')],
            ],
            [
                'This trainee successfully completed the IADC Well Control Knowledge Assessment.',
                'The assessment was completed for '.$certificate->subject_name.'. Keep this report with the Course Completion Card.',
            ],
            612,
            792,
        );
    }

    private function renderCompletionCard(Certificate $certificate, bool $back): string
    {
        if ($back) {
            return $this->pdfPage(
                'Course Completion Card - Back',
                [],
                [
                    'This individual has successfully completed a well control course at an institution accredited by the International Association of Drilling Contractors.',
                    'For scheduling training or replacement of a lost card, please call the training provider with the information provided on this completion card.',
                    'To verify validity, please visit the IADC website:',
                    'www.iadc.org/wellsharp',
                ],
                792,
                612,
            );
        }

        $subject = $certificate->exam?->subject;
        $courseName = collect([
            $certificate->subject_name,
            $subject?->level?->name,
            $subject?->stacks?->pluck('name')->join(', '),
        ])->filter()->join(', ');
        $provider = $certificate->trainingClass?->provider;

        return $this->pdfPage(
            'Course Completion Card - Front',
            [
                ['Trainee name', $certificate->student_name],
                ['Course name', $courseName ?: $certificate->exam_name],
                ['Supplement name', $subject?->supplements?->pluck('name')->join(', ') ?: 'Not configured'],
                ['Completion date', $this->date($certificate->issued_at, 'j F Y')],
                ['Expiration date', $this->date($certificate->expires_at ?: $certificate->issued_at?->copy()->addYears(2), 'j F Y')],
                ['Provider', $certificate->provider_name ?: $provider?->name ?: 'Not configured'],
                ['Provider number', $provider?->provider_number ?: 'Not configured'],
                ['Phone number', $provider?->phone ?: 'Not configured'],
                ['Instructor name', $certificate->instructor_name ?: 'Not configured'],
                ['Certificate number', $certificate->certificate_number],
            ],
            [],
            792,
            612,
        );
    }

    /** @param array<int, array{0: string, 1: string}> $fields
     * @param  array<int, string>  $paragraphs
     */
    private function pdfPage(string $title, array $fields, array $paragraphs, int $width, int $height): string
    {
        $commands = [];
        $commands[] = '1 1 1 rg 0 0 '.$width.' '.$height.' re f';
        $commands[] = '.05 .16 .25 rg 0 '.($height - 112).' '.$width.' 112 re f';
        $commands[] = '.84 .04 .12 rg 0 '.($height - 118).' '.$width.' 6 re f';
        $commands[] = $this->text('IADC WELLSHARP', 44, $height - 49, 17, 'F2', '1 1 1');
        $commands[] = $this->text($title, 44, $height - 83, 22, 'F2', '1 1 1');

        $y = $height - 158;
        if ($fields !== []) {
            foreach ($fields as [$label, $value]) {
                $wrapped = $this->wrap((string) $value, $width > 700 ? 60 : 48);
                $commands[] = $this->text(strtoupper($label), 48, $y, 8, 'F2', '.36 .46 .54');
                foreach ($wrapped as $lineIndex => $line) {
                    $commands[] = $this->text($line, 190, $y - ($lineIndex * 14), 12, 'F1', '.08 .18 .27');
                }
                $y -= max(28, count($wrapped) * 14 + 10);
                $commands[] = '.84 .88 .91 RG 48 '.$y.' '.($width - 96).' .6 re f';
                $y -= 15;
            }
        }

        if ($paragraphs !== []) {
            $y = min($y, $height - 180);
            foreach ($paragraphs as $paragraph) {
                foreach ($this->wrap($paragraph, $width > 700 ? 92 : 78) as $line) {
                    $commands[] = $this->text($line, 48, $y, 12, 'F1', '.20 .30 .37');
                    $y -= 18;
                }
                $y -= 12;
            }
        }

        $commands[] = '.96 .97 .98 rg 0 24 '.$width.' 38 re f';
        $commands[] = $this->text('Generated by WellSharp', 44, 39, 9, 'F1', '.38 .47 .54');

        return $this->assemble(implode("\n", $commands), $width, $height);
    }

    private function text(string $value, int $x, int $y, int $size, string $font, string $color): string
    {
        $value = $this->pdfText($value);
        $value = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);

        return sprintf('%s rg BT /%s %d Tf %d %d Td (%s) Tj ET', $color, $font, $size, $x, $y, $value);
    }

    /** @return array<int, string> */
    private function wrap(string $value, int $length): array
    {
        $value = $this->pdfText($value);

        return $value === '' ? ['Not configured'] : (array) wordwrap($value, $length, "\n", true);
    }

    private function pdfText(string $value): string
    {
        $converted = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) : false;

        return $converted !== false && $converted !== null ? $converted : (string) preg_replace('/[^\x20-\x7E]/', '?', $value);
    }

    private function date(mixed $date, string $format): string
    {
        return $date?->format($format) ?: 'Not configured';
    }

    private function assemble(string $stream, int $width, int $height): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$width.' '.$height.'] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            '<< /Length '.strlen($stream).' >>\nstream\n'.$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }
}
