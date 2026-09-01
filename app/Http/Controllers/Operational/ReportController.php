<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Certificates\IssueCertificateAction;
use App\Actions\Exams\ReleaseExamAttemptAction;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\EffectiveScoreService;
use App\Services\ExamScoringService;
use App\Services\OperationalReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const CHART_HEADERS = [
        'Assessment', 'Trainees Assessed', '# Passed', '# Failed', 'Passing Rate', 'Average Score',
        'Trainees Retaking Exam', 'Retake # Passed', 'Retake # Failed', 'Retake Passing Rate', 'Retake Average Score',
    ];

    public function results(Request $request, OperationalReportingService $reports): View
    {
        $classes = $reports->accessibleClasses(auth()->user());
        $attempts = $reports->allAttempts($classes)->filter(fn (ExamAttempt $attempt): bool => $attempt->score !== null);

        // `passed`/`score` here are the effective (Skills Score-aware) figures -
        // this page reports Class results, so it must agree with the CSV export
        // and certificate eligibility on what "passed" means for a trainee.
        $attemptsJson = $attempts->map(function (ExamAttempt $attempt): array {
            return [
                'exam_id' => $attempt->exam_id,
                'exam_name' => $attempt->exam?->name ?: 'Assessment',
                'subject_name' => $attempt->exam?->subject?->name ?: 'Not assigned',
                'course_id' => $attempt->exam?->course_id,
                'student_user_id' => $attempt->student_user_id,
                'attempt_number' => $attempt->attempt_number,
                'passed' => $attempt->effective_passed,
                'score' => $attempt->effective_score,
                'occurred_at' => ($attempt->submitted_at ?: $attempt->started_at)?->toDateTimeString(),
            ];
        })->values();

        return view('operational.assessment-results', [
            'workspaceClass' => 'assessment-results-workspace',
            'courses' => $classes->pluck('course')->unique('id')->sortBy('name'),
            'attemptsJson' => $attemptsJson,
            'initialFilters' => $request->only(['course_id', 'role', 'date_range', 'start_date', 'end_date']),
        ]);
    }

    public function exportResults(Request $request, OperationalReportingService $reports): StreamedResponse
    {
        $classes = $reports->accessibleClasses(auth()->user());
        $attempts = $reports->filteredAttempts($classes, $request);
        $rows = $reports->assessmentRows($attempts);
        $totals = $reports->totalsRow($rows);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Assessment Comparison');
        $sheet->fromArray(self::CHART_HEADERS, null, 'A1');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($this->exportRow($row), null, 'A'.$rowIndex);
            $rowIndex++;
        }
        $sheet->fromArray($this->exportRow($totals), null, 'A'.$rowIndex);

        $lastColumn = 'K';
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->getStyle('A'.$rowIndex.':'.$lastColumn.$rowIndex)->getFont()->setBold(true);
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'wellsharp-assessment-comparison.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function show(ExamAttempt $attempt, OperationalReportingService $reports, ExamScoringService $scoring): View
    {
        abort_unless($reports->canViewAttempt($attempt, auth()->user()), 403);
        $attempt->load(['student.profile', 'exam.subject', 'schedule.group', 'schedule.trainingClass.provider', 'releasedBy.profile']);
        $breakdown = $attempt->score === null ? [] : $scoring->breakdown($attempt);

        return view('operational.attempt-report', [
            'workspaceClass' => 'assessment-results-workspace',
            'attempt' => $attempt,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Powers the Score Report popup on the Class Dashboard's Scores &
     * Reports tab — a short, trainee-facing summary (unlike show(), which
     * renders the full internal question-by-question breakdown page).
     */
    public function summary(ExamAttempt $attempt, OperationalReportingService $reports, ExamScoringService $scoring, EffectiveScoreService $effectiveScore): JsonResponse
    {
        abort_unless($reports->canViewAttempt($attempt, auth()->user()), 403);
        $attempt->load(['student.profile', 'exam.subject.stacks']);
        $breakdown = $attempt->score === null ? [] : $scoring->breakdown($attempt);
        $solutionsByQuestionId = $attempt->attemptQuestions->pluck('question.solution_text', 'question.public_id');

        $topics = collect($breakdown)
            ->reject(fn (array $question): bool => $question['is_correct'])
            ->map(fn (array $question): array => [
                'title' => $question['question_text'],
                'note' => $solutionsByQuestionId->get($question['question_id']) ?: 'Review this topic with your instructor.',
            ])->values()->all();

        // Result/score shown here are the *effective* (Skills Score-aware) figures,
        // since this is the "did the trainee pass" summary - not the raw Knowledge
        // Exam calculation, which stays available separately for transparency.
        $effective = $attempt->score === null ? null : $effectiveScore->forAttempt($attempt);

        return response()->json([
            'name' => $attempt->student?->display_name ?: $attempt->student?->wellsharp_id ?: 'Unknown trainee',
            'assessment' => $attempt->exam?->subject?->name ?: ($attempt->exam?->name ?: 'Assessment'),
            'stack' => $attempt->exam?->subject?->stacks->pluck('name')->join(', ') ?: 'Not configured',
            'assessmentDate' => $attempt->submitted_at?->format('F j, Y g:i A') ?: 'Not submitted',
            'knowledgeScore' => $attempt->score !== null ? number_format((float) $attempt->score, 0) : null,
            'score' => $effective !== null ? number_format($effective['score'], 0) : null,
            'passed' => $effective['passed'] ?? null,
            'overridden' => $effective['overridden'] ?? false,
            'topics' => $topics,
        ]);
    }

    public function release(ExamAttempt $attempt, OperationalReportingService $reports, ReleaseExamAttemptAction $release, IssueCertificateAction $issuer): RedirectResponse
    {
        abort_unless($reports->canViewAttempt($attempt, auth()->user()), 403);
        $released = $release->execute($attempt, auth()->user());
        $certificate = $released->status->value === 'submitted' ? $issuer->execute($released) : null;

        return back();
    }

    /** @param array<string, mixed> $row */
    private function exportRow(array $row): array
    {
        return [
            $row['name'] ?? '', $row['trainees'], $row['passed'], $row['failed'], $row['rate'], $row['average'],
            $row['retaking'], $row['retake_passed'], $row['retake_failed'], $row['retake_rate'], $row['retake_average'],
        ];
    }
}
