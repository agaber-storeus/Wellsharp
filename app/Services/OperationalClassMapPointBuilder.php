<?php

namespace App\Services;

use App\Enums\CertificateDocumentType;
use App\Enums\ClassStatus;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\TrainingClass;
use Illuminate\Support\Collection;

class OperationalClassMapPointBuilder
{
    public function __construct(private readonly KnowledgeResultService $knowledgeResults)
    {
    }

    /** @param Collection<int, TrainingClass> $classes */
    public function build(Collection $classes): array
    {
        return $classes->map(function (TrainingClass $trainingClass): array {
            $status = $trainingClass->status;

            return [
                'classNumber' => $trainingClass->class_number,
                'title' => $trainingClass->displayTitle(),
                'location' => $trainingClass->providerLocation?->location ?: $trainingClass->provider?->address ?: $trainingClass->provider?->name ?: 'Location not assigned',
                'provider' => $trainingClass->provider?->name ?: 'Provider not assigned',
                'status' => $status->value,
                'group' => match ($status) {
                    ClassStatus::Active => 'ongoing',
                    ClassStatus::Planned => 'upcoming',
                    ClassStatus::Completed, ClassStatus::Cancelled => 'past',
                },
                'statusLabel' => $status->label(),
                'startsAt' => $trainingClass->starts_at?->toIso8601String(),
                'endsAt' => $trainingClass->ends_at?->toIso8601String(),
                'durationDays' => $this->durationDays($trainingClass),
                'lat' => $trainingClass->providerLocation?->latitude ?? $trainingClass->provider?->latitude,
                'lng' => $trainingClass->providerLocation?->longitude ?? $trainingClass->provider?->longitude,
            ];
        })->values()->all();
    }

    /** @param Collection<int, TrainingClass> $classes */
    public function buildModalData(Collection $classes): array
    {
        $attempts = $classes->flatMap(fn(TrainingClass $trainingClass) => $trainingClass->examSchedules->flatMap(fn($schedule) => $schedule->attempts));
        $certificates = Certificate::query()->with('documents')->whereIn('exam_attempt_id', $attempts->pluck('id'))->get()->keyBy('exam_attempt_id');

        return $classes->mapWithKeys(function (TrainingClass $trainingClass) use ($certificates): array {
            $status = $trainingClass->status;
            $statusClass = match ($status) {
                ClassStatus::Active => 'state-green',
                ClassStatus::Planned => 'state-blue',
                ClassStatus::Completed, ClassStatus::Cancelled => 'state-ended',
            };
            $statusDisplayLabel = match ($status) {
                ClassStatus::Active => 'Active',
                ClassStatus::Planned => 'Not Started',
                ClassStatus::Completed, ClassStatus::Cancelled => 'Test Ended',
            };
            $attemptsByStudent = $trainingClass->examSchedules
                ->flatMap(fn($schedule) => $schedule->attempts)
                ->groupBy('student_user_id');
            $primarySchedule = $this->primarySchedule($trainingClass);

            $scoreRows = $trainingClass->enrollments
                ->sortBy(fn($enrollment) => $enrollment->student?->display_name ?: $enrollment->student?->wellsharp_id)
                ->map(function ($enrollment) use ($certificates, $attemptsByStudent): array {
                    $attempt = $attemptsByStudent->get($enrollment->student_user_id, collect())
                        ->sortByDesc('attempt_number')
                        ->first();
                    $certificate = $attempt ? $certificates->get($attempt->id) : null;

                    return $this->scoreRow($enrollment, $attempt, $certificate);
                })->values()->all();

            return [
                $trainingClass->public_id => [
                    'details' => [
                        ['Class ID:', $primarySchedule?->class_id ?: ''],
                        ['Class Title or ID:', $trainingClass->displayTitle()],
                        ['Class Status:', $statusDisplayLabel, $statusClass],
                        ['Class Dates:', $this->dateRange($trainingClass)],
                        ['Exam Date/Time:', $this->examAvailability($trainingClass)],
                        ['Started On:', $this->startedAt($trainingClass, $status)],
                        ['Ended On:', $this->endedAt($trainingClass, $status)],
                        ['Address:', $trainingClass->providerLocation?->location ?: $trainingClass->provider?->address ?: $trainingClass->provider?->name ?: 'Not assigned'],
                        ['Course Level:', $trainingClass->course->name],
                        ['Stacks Offered:', $primarySchedule?->stack_offered ?: ''],
                        ['Supplement Offered:', $primarySchedule?->supplement_offered ?: 'No Supplement Offered'],
                        ['Instructor:', $trainingClass->instructor?->display_name ?: 'Not assigned'],
                        ['Class Language:', 'English'],
                    ],
                    'codeRows' => $trainingClass->enrollments
                        ->map(fn($enrollment): array => [
                            'studentId' => $enrollment->student?->public_id,
                            'name' => $enrollment->student?->display_name ?: $enrollment->student?->wellsharp_id ?: 'Unknown trainee',
                            'username' => $enrollment->student?->wellsharp_id ?: '—',
                            'company' => $enrollment->student?->profile?->company ?: '—',
                        ])->values()->all(),
                    'studentPasswordsUrl' => route(auth()->user()->hasRole('proctor') ? 'proctor.classes.student-passwords' : 'instructor.classes.student-passwords', $trainingClass),
                    'scoreRows' => $scoreRows,
                    'examControl' => [
                        'status' => $status->value,
                        'controlUrl' => route(auth()->user()->hasRole('proctor') ? 'proctor.classes.exam-control' : 'instructor.classes.exam-control', $trainingClass),
                        'verifyUrl' => route(auth()->user()->hasRole('proctor') ? 'proctor.proctor-id.verify' : 'instructor.proctor-id.verify'),
                        'scheduledFor' => $trainingClass->examSchedules->map(fn($schedule): array => [
                            'name' => $schedule->exam?->name ?: 'Linked Exam',

                            'start' => $schedule->start_date && $schedule->start_time
                                ? $schedule->start_date->format('Y-m-d') . ' ' . $schedule->start_time
                                : $schedule->start_date?->format('Y-m-d'),

                            'end' => $schedule->end_date && $schedule->end_time
                                ? $schedule->end_date->format('Y-m-d') . ' ' . $schedule->end_time
                                : $schedule->end_date?->format('Y-m-d'),

                            'status' => $schedule->status->value,
                        ])->values()->all(),
                    ],
                ]
            ];
        })->all();
    }

    /**
     * Builds one Class Dashboard roster row for a single Enrollment - the
     * authoritative shape both buildModalData() (bulk, preloaded attempts and
     * certificates to avoid N+1) and scoreRowForEnrollment() (single
     * Enrollment, used after a Practical / Skills Score save so the frontend can update
     * the row's Certificate cell without a page reload) build from, so the
     * two paths can never drift into different shapes.
     */
    private function scoreRow(Enrollment $enrollment, ?ExamAttempt $attempt, ?Certificate $certificate): array
    {
        $state = $attempt ? match ($attempt->status->value) {
            'submitted' => 'complete',
            'expired' => 'noshow',
            default => 'inprogress',
        } : 'notstarted';
        $documentsByType = $certificate?->documents->keyBy(fn($document) => $document->type->value);
        $fullDocument = $documentsByType?->get(CertificateDocumentType::FullCertificate->value);
        $frontDocument = $documentsByType?->get(CertificateDocumentType::CompletionCardFront->value);
        $backDocument = $documentsByType?->get(CertificateDocumentType::CompletionCardBack->value);
        $isProctor = auth()->user()->hasRole('proctor');
        $result = $attempt && $attempt->score !== null ? $this->knowledgeResults->resolve($attempt) : null;

        return [
            'name' => $enrollment->student?->display_name ?: $enrollment->student?->wellsharp_id ?: 'Unknown trainee',
            'skillsScore' => $enrollment->skills_score,
            'skillsScoreUrl' => route($isProctor ? 'proctor.enrollments.skills-score' : 'instructor.enrollments.skills-score', $enrollment),
            'score' => $result ? number_format((float) $result['final_knowledge_score'], 2, '.', '') : null,
            'passed' => $result['final_passed'] ?? null,
            'state' => $state,
            'attemptNumber' => $attempt?->attempt_number,
            'releasedAt' => $attempt?->released_at?->format('Y-m-d H:i'),
            'reportUrl' => $attempt ? route($isProctor ? 'proctor.analytics.attempts.summary' : 'instructor.analytics.attempts.summary', $attempt) : null,
            'releaseUrl' => $attempt ? route($isProctor ? 'proctor.analytics.attempts.release' : 'instructor.analytics.attempts.release', $attempt) : null,
            'certificateDownloadUrl' => $fullDocument ? route('certificates.documents.download', [$certificate, $fullDocument]) : ($frontDocument ? route('certificates.documents.download', [$certificate, $frontDocument]) : null),
            'certificateFrontUrl' => $frontDocument ? route('certificates.documents.standalone', [$certificate, $frontDocument]) : null,
            'certificateBackUrl' => $backDocument ? route('certificates.documents.standalone', [$certificate, $backDocument]) : null,
            'certificateNumber' => $certificate?->certificate_number,
        ];
    }

    /**
     * The single-Enrollment counterpart of scoreRow(), used to return the
     * authoritative roster row state right after a Practical / Skills Score
     * save so the frontend can refresh the independent score value.
     */
    public function scoreRowForEnrollment(Enrollment $enrollment): array
    {
        $enrollment->loadMissing('student.profile');
        $attempt = ExamAttempt::query()
            ->whereHas('schedule', fn($query) => $query->where('training_class_id', $enrollment->class_id))
            ->where('student_user_id', $enrollment->student_user_id)
            ->orderByDesc('attempt_number')
            ->first();
        $attempt?->loadMissing('exam');
        $certificate = $attempt
            ? Certificate::query()->with('documents')->where('exam_attempt_id', $attempt->id)->first()
            : null;

        return $this->scoreRow($enrollment, $attempt, $certificate);
    }

    private function dateRange(TrainingClass $trainingClass): string
    {
        if (!$trainingClass->starts_at && !$trainingClass->ends_at) {
            return 'Not scheduled';
        }

        if (!$trainingClass->starts_at) {
            return $trainingClass->ends_at->format('F j');
        }

        if (!$trainingClass->ends_at) {
            return $trainingClass->starts_at->format('F j');
        }

        return $trainingClass->starts_at->format('F j') . ' - ' . $trainingClass->ends_at->format('j');
    }

    private function durationDays(TrainingClass $trainingClass): ?int
    {
        if (!$trainingClass->starts_at || !$trainingClass->ends_at) {
            return null;
        }

        return max(1, $trainingClass->starts_at->copy()->startOfDay()->diffInDays($trainingClass->ends_at->copy()->startOfDay()) + 1);
    }

    private function durationLabel(TrainingClass $trainingClass): string
    {
        $days = $this->durationDays($trainingClass);

        return $days ? $days . ' ' . ($days === 1 ? 'day' : 'days') : 'Duration not configured';
    }

    private function primarySchedule(TrainingClass $trainingClass): mixed
    {
        return $trainingClass->examSchedules
            ->sortBy(fn($schedule): string => ($schedule->start_date?->toDateString() ?: '9999-12-31') . '-' . str_pad((string) $schedule->getKey(), 12, '0', STR_PAD_LEFT))
            ->first();
    }

    public function examAvailability(TrainingClass $trainingClass): string
    {
        $schedule = $this->primarySchedule($trainingClass);

        if (!$schedule || !$schedule->start_date) {
            return 'n/a';
        }

        $time = $schedule->start_time ?: '00:00:00';

        return $schedule->start_date
            ->copy()
            ->setTimeFromTimeString($time)
            ->format('m/d/Y g:i A');
    }

    private function scheduleDateRange($startDate, $endDate): ?string
    {
        if (!$startDate && !$endDate) {
            return null;
        }

        if (!$startDate) {
            return $endDate->format('F j, Y');
        }

        if (!$endDate || $startDate->equalTo($endDate)) {
            return $startDate->format('F j, Y');
        }

        return $startDate->format('F j') . ' - ' . $endDate->format('j, Y');
    }

    private function startedAt(TrainingClass $trainingClass, ClassStatus $status): string
    {
        if ($status !== ClassStatus::Active) {
            return 'n/a';
        }

        $override = $trainingClass->examSchedules
            ->pluck('override_started_at')
            ->filter()
            ->sort()
            ->first();

        return ($trainingClass->actual_started_at ?: $override ?: $trainingClass->starts_at)?->format('F j, Y g:i A') ?: 'n/a';
    }

    private function endedAt(TrainingClass $trainingClass, ClassStatus $status): string
    {
        if (!in_array($status, [ClassStatus::Completed, ClassStatus::Cancelled], true)) {
            return 'n/a';
        }

        $override = $trainingClass->examSchedules
            ->pluck('override_ended_at')
            ->filter()
            ->sortDesc()
            ->first();

        return ($trainingClass->actual_ended_at ?: $override ?: $trainingClass->ends_at)?->format('F j, Y g:i A') ?: 'n/a';
    }
}
