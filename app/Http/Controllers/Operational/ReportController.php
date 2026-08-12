<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Certificates\IssueCertificateAction;
use App\Actions\Exams\ReleaseExamAttemptAction;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\ExamScoringService;
use App\Services\OperationalReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function results(Request $request, OperationalReportingService $reports): View
    {
        $classes = $reports->accessibleClasses(auth()->user());
        $attempts = $reports->filteredAttempts($classes, $request);

        return view('operational.assessment-results', [
            'workspaceClass' => 'assessment-results-workspace',
            'courses' => $classes->pluck('course')->unique('id')->sortBy('name'),
            'results' => $reports->assessmentRows($attempts),
            'attemptRows' => $attempts,
            'filters' => $request->only(['course_id', 'role', 'date_range', 'start_date', 'end_date']),
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

    public function release(ExamAttempt $attempt, OperationalReportingService $reports, ReleaseExamAttemptAction $release, IssueCertificateAction $issuer): RedirectResponse
    {
        abort_unless($reports->canViewAttempt($attempt, auth()->user()), 403);
        $released = $release->execute($attempt, auth()->user());
        $certificate = $released->status->value === 'submitted' ? $issuer->execute($released) : null;

        return back()->with('status', $certificate
            ? 'Trainee exam released, scored, and certificate '.$certificate->certificate_number.' issued.'
            : 'Trainee exam released and scored.');
    }
}
