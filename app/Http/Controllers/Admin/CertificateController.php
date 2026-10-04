<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateStatus;
use App\Enums\KnowledgeControlType;
use App\Actions\Certificates\IssueCertificateAction;
use App\Actions\Exams\ManageKnowledgeScoreControlAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReasonRequest;
use App\Http\Requests\Admin\StoreKnowledgeScoreControlRequest;
use App\Models\Certificate;
use App\Models\ExamAttemptScoreControl;
use App\Services\AuditRecorder;
use App\Services\KnowledgeResultService;
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

    public function show(Certificate $certificate, KnowledgeResultService $results): View
    {
        $certificate->load([
            'student.profile',
            'exam.subject',
            'schedule.group',
            'trainingClass',
            'provider',
            'instructor.profile',
            'attempt.attemptQuestions.question.options',
            'attempt.scoreControls.createdBy.profile',
            'attempt.scoreControls.revertedBy.profile',
            'attempt.scoreControls.attemptQuestion.question',
            'documents',
        ]);

        $examReview = [];
        $knowledgeResult = null;
        if ($certificate->attempt) {
            $knowledgeResult = $results->resolve($certificate->attempt);
            $questions = $certificate->attempt->attemptQuestions->keyBy('id');
            $examReview = collect($knowledgeResult['breakdown'])
                ->map(function (array $row) use ($questions): array {
                    $question = $questions->get($row['id'])?->question;

                    return $row + [
                        'code' => $question?->code,
                        'type_label' => $question?->type?->label(),
                        'difficulty_label' => $question?->difficulty?->label(),
                        'solution_text' => $question?->solution_text,
                    ];
                })
                ->all();
        }

        return view('admin.certificates.show', compact('certificate', 'examReview', 'knowledgeResult'));
    }

    public function storeKnowledgeControl(StoreKnowledgeScoreControlRequest $request, Certificate $certificate, ManageKnowledgeScoreControlAction $action): \Illuminate\Http\RedirectResponse
    {
        abort_unless($certificate->attempt, 404);
        $data = $request->validated();
        $action->create($certificate->attempt, KnowledgeControlType::from($data['type']), $data, $request->user());

        return back()->with('status', 'Knowledge score control added.');
    }

    public function revertKnowledgeControl(ReasonRequest $request, Certificate $certificate, ExamAttemptScoreControl $control, ManageKnowledgeScoreControlAction $action): \Illuminate\Http\RedirectResponse
    {
        abort_unless($certificate->attempt, 404);
        $action->revert($certificate->attempt, $control, $request->validated('reason'), $request->user());

        return back()->with('status', 'Knowledge score control reverted.');
    }

    public function restoreKnowledgeControls(ReasonRequest $request, Certificate $certificate, ManageKnowledgeScoreControlAction $action): \Illuminate\Http\RedirectResponse
    {
        abort_unless($certificate->attempt, 404);
        $action->restore($certificate->attempt, $request->validated('reason'), $request->user());

        return back()->with('status', 'Calculated Knowledge result restored.');
    }

    public function issue(Certificate $certificate, IssueCertificateAction $issuer): \Illuminate\Http\RedirectResponse
    {
        abort_unless($certificate->attempt, 404);
        $issued = $issuer->execute($certificate->attempt);

        return back()->with('status', $issued ? 'Certificate is issued.' : 'The current Knowledge result is not eligible.');
    }

    public function revoke(ReasonRequest $request, Certificate $certificate, AuditRecorder $audit): \Illuminate\Http\RedirectResponse
    {
        if ($certificate->status !== CertificateStatus::Revoked) {
            $before = $certificate->toArray();
            $certificate->update(['status' => CertificateStatus::Revoked, 'revoked_at' => now(), 'revocation_reason' => $request->validated('reason')]);
            $audit->record('certificate.revoked', $certificate, $before, $certificate->fresh()->toArray(), $request->validated('reason'));
        }

        return back()->with('status', 'Certificate revoked.');
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
