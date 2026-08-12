<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $certificates = $this->filteredQuery($request)
            ->orderByDesc('issued_at')
            ->paginate(25)
            ->withQueryString();
        $initialCertificates = $certificates->getCollection()->map(fn (Certificate $certificate): array => $this->certificatePayload($certificate))->values();
        $initialMeta = $this->paginationMeta($certificates);

        return view('admin.certificates.index', compact('certificates', 'initialCertificates', 'initialMeta', 'search', 'status'));
    }

    public function data(Request $request): JsonResponse
    {
        $sort = (string) $request->input('sort', 'issued_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['certificate_number', 'student_name', 'subject_name', 'class_number', 'provider_name', 'score', 'issued_at', 'status'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'issued_at';
        $certificates = $this->filteredQuery($request)
            ->orderBy($sort, $direction)
            ->paginate(25, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $certificates->getCollection()->map(fn (Certificate $certificate): array => $this->certificatePayload($certificate))->values(),
            'meta' => $this->paginationMeta($certificates),
        ]);
    }

    public function show(Certificate $certificate): View
    {
        $certificate->load(['student.profile', 'exam.subject', 'schedule.group', 'trainingClass', 'provider', 'instructor.profile', 'attempt.attemptQuestions.question', 'documents']);

        return view('admin.certificates.show', compact('certificate'));
    }

    private function filteredQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('search'));
        $status = (string) $request->input('status');

        return Certificate::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('certificate_number', 'like', '%'.$search.'%')
                        ->orWhere('student_name', 'like', '%'.$search.'%')
                        ->orWhere('student_wellsharp_id', 'like', '%'.$search.'%')
                        ->orWhere('class_number', 'like', '%'.$search.'%')
                        ->orWhere('subject_name', 'like', '%'.$search.'%')
                        ->orWhere('provider_name', 'like', '%'.$search.'%');
                });
            })
            ->when(in_array($status, array_column(CertificateStatus::cases(), 'value'), true), fn (Builder $query): Builder => $query->where('status', $status));
    }

    /** @return array<string, int|null> */
    private function paginationMeta($certificates): array
    {
        return [
            'current_page' => $certificates->currentPage(),
            'last_page' => $certificates->lastPage(),
            'total' => $certificates->total(),
            'from' => $certificates->firstItem(),
            'to' => $certificates->lastItem(),
        ];
    }

    /** @return array<string, mixed> */
    private function certificatePayload(Certificate $certificate): array
    {
        return [
            'id' => $certificate->public_id,
            'certificate_number' => $certificate->certificate_number,
            'student_name' => $certificate->student_name,
            'student_wellsharp_id' => $certificate->student_wellsharp_id,
            'subject_name' => $certificate->subject_name,
            'exam_name' => $certificate->exam_name,
            'class_number' => $certificate->class_number,
            'provider_name' => $certificate->provider_name,
            'score' => (float) $certificate->score,
            'issued_at' => $certificate->issued_at?->format('Y-m-d'),
            'expires_at' => $certificate->expires_at?->format('Y-m-d'),
            'document_count' => $certificate->documents()->count(),
            'status' => $certificate->status->value,
            'status_label' => $certificate->status->label(),
            'certificate_url' => route('admin.certificates.show', $certificate),
        ];
    }
}
