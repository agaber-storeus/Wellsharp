<?php

namespace App\Http\Controllers;

use App\Enums\CertificateDocumentType;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Services\CertificatePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CertificateDocumentController extends Controller
{
    public function show(Request $request, Certificate $certificate): View
    {
        $this->loadCertificate($certificate);
        abort_unless($this->canView($certificate), 403);

        return view('certificates.show', $this->viewContext($request, $certificate));
    }

    public function document(Request $request, Certificate $certificate, CertificateDocument $document): View
    {
        $this->loadCertificate($certificate);
        $this->ensureDocumentBelongsToCertificate($certificate, $document);
        abort_unless($this->canView($certificate), 403);

        return view('certificates.document', $this->viewContext($request, $certificate) + compact('document'));
    }

    /**
     * The bare completion card, with no app chrome — this is what the
     * Class Dashboard's Certificate column Front/Back buttons open in a
     * new tab, matching the standalone card design exactly.
     */
    public function standalone(Certificate $certificate, CertificateDocument $document): View
    {
        $this->loadCertificate($certificate);
        $this->ensureDocumentBelongsToCertificate($certificate, $document);
        abort_unless($this->canView($certificate), 403);
        abort_unless(in_array($document->type, [CertificateDocumentType::CompletionCardFront, CertificateDocumentType::CompletionCardBack], true), 404);

        return view('certificates.standalone', compact('certificate', 'document'));
    }

    public function download(Certificate $certificate, CertificateDocument $document, CertificatePdfService $pdf): Response
    {
        return $this->pdfResponse($certificate, $document, $pdf, 'attachment');
    }

    /**
     * The same real certificate PDF as the download, streamed inline so the
     * Options column's "Preview Certificate" action can display the actual
     * branded Certificate of Completion + wallet card, with the real
     * student's data, instead of a plain HTML approximation.
     */
    public function preview(Certificate $certificate, CertificateDocument $document, CertificatePdfService $pdf): Response
    {
        return $this->pdfResponse($certificate, $document, $pdf, 'inline');
    }

    private function pdfResponse(Certificate $certificate, CertificateDocument $document, CertificatePdfService $pdf, string $disposition): Response
    {
        $this->loadCertificate($certificate);
        $this->ensureDocumentBelongsToCertificate($certificate, $document);
        abort_unless($this->canView($certificate), 403);

        $contents = $pdf->render($document->load('certificate'));
        $filename = str($certificate->student_name.'-'.$document->title)->slug('-').'.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function loadCertificate(Certificate $certificate): void
    {
        $certificate->load([
            'student.profile',
            'exam.subject.level',
            'exam.subject.stacks',
            'exam.subject.supplements',
            'schedule.group',
            'trainingClass.provider',
            'attempt.attemptQuestions.question',
            'documents',
        ]);
    }

    private function ensureDocumentBelongsToCertificate(Certificate $certificate, CertificateDocument $document): void
    {
        abort_unless((int) $document->certificate_id === (int) $certificate->getKey(), 404);
    }

    /** @return array<string, mixed> */
    private function viewContext(Request $request, Certificate $certificate): array
    {
        return [
            'certificate' => $certificate,
            'documents' => $certificate->documents->keyBy(fn ($document): string => $document->type->value),
            'layout' => $request->user()->isAdmin() ? 'layouts.admin' : ($request->user()->hasRole('student') ? 'layouts.student' : 'layouts.operational'),
            'contentSection' => $request->user()->isAdmin() ? 'admin-content' : ($request->user()->hasRole('student') ? 'student-content' : 'operational-content'),
            'studentFlowSchedule' => null,
            'flowStep' => '',
        ];
    }

    private function canView(Certificate $certificate): bool
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->hasRole('student')) {
            return (int) $certificate->student_user_id === (int) $user->getKey();
        }
        if (! $user->hasRole('proctor') && ! $user->hasRole('instructor')) {
            return false;
        }

        return $user->isActive();
    }
}
