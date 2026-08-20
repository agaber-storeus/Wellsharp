<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OperationalReportingService
{
    /** @return Collection<int, TrainingClass> */
    public function accessibleClasses(User $user): Collection
    {
        return TrainingClass::query()
            ->with([
                'course.level',
                'staffAssignments',
                'examSchedules.exam.subject',
                'examSchedules.attempts.student.profile',
                'examSchedules.attempts.exam.subject',
            ])
            ->withCount('enrollments')
            ->latest()
            ->get();
    }

    /**
     * Every exam attempt the given classes make visible, regardless of request filters.
     * Callers that need the full authorized dataset (e.g. to hand to a client-side Alpine
     * table for filtering/sorting/pagination) should use this instead of filteredAttempts().
     *
     * @param  Collection<int, TrainingClass>  $classes
     * @return Collection<int, ExamAttempt>
     */
    public function allAttempts(Collection $classes): Collection
    {
        return $classes
            ->flatMap(function (TrainingClass $trainingClass): Collection {
                return $trainingClass->examSchedules->flatMap(function ($schedule) use ($trainingClass): Collection {
                    return $schedule->attempts->map(function (ExamAttempt $attempt) use ($schedule, $trainingClass): ExamAttempt {
                        return $attempt->setRelation('schedule', $schedule)->setRelation('trainingClass', $trainingClass);
                    });
                });
            })
            ->values();
    }

    /**
     * Server-side filtered attempts, used by the Excel export which must apply the
     * requester's current filters itself (the results page filters client-side in Alpine).
     *
     * @param  Collection<int, TrainingClass>  $classes
     * @return Collection<int, ExamAttempt>
     */
    public function filteredAttempts(Collection $classes, Request $request): Collection
    {
        $courseId = $request->integer('course_id') ?: null;
        $role = (string) $request->input('role', 'Trainees');
        [$from, $to] = $this->dateBounds($request);

        if (in_array($role, ['Instructors', 'Proctors'], true)) {
            return collect();
        }

        return $this->allAttempts($classes)
            ->filter(function (ExamAttempt $attempt) use ($courseId, $from, $to): bool {
                if ($courseId && (int) $attempt->exam?->course_id !== $courseId) {
                    return false;
                }

                $occurredAt = $attempt->submitted_at ?: $attempt->started_at;
                if ($from && (! $occurredAt || $occurredAt->lt($from))) {
                    return false;
                }
                if ($to && (! $occurredAt || $occurredAt->gt($to))) {
                    return false;
                }

                return true;
            })
            ->sortByDesc(fn (ExamAttempt $attempt) => $attempt->submitted_at ?: $attempt->started_at)
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function assessmentRows(Collection $attempts): Collection
    {
        return $attempts
            ->filter(fn (ExamAttempt $attempt): bool => $attempt->score !== null)
            ->groupBy('exam_id')
            ->map(function (Collection $examAttempts): array {
                $exam = $examAttempts->first()->exam;

                return $this->summarizeAttempts($examAttempts, $exam?->name ?: 'Assessment', $exam?->subject?->name ?: 'Not assigned', $exam?->getKey());
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * Totals footer for the assessment comparison table: counts are summed across rows,
     * rates/averages are the mean of each row's percentage — matching the reference design
     * (a trainee who sits multiple assessments is counted once per row, not deduped overall).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function totalsRow(Collection $rows): array
    {
        return [
            'name' => '',
            'subject' => null,
            'trainees' => $rows->sum('trainees'),
            'passed' => $rows->sum('passed'),
            'failed' => $rows->sum('failed'),
            'rate' => $this->averagePercent($rows->pluck('rate')),
            'average' => $this->averagePercent($rows->pluck('average')),
            'retaking' => $rows->sum('retaking'),
            'retake_passed' => $rows->sum('retake_passed'),
            'retake_failed' => $rows->sum('retake_failed'),
            'retake_rate' => $this->averagePercent($rows->pluck('retake_rate')),
            'retake_average' => $this->averagePercent($rows->pluck('retake_average')),
        ];
    }

    /** @param Collection<int, string> $percentages */
    private function averagePercent(Collection $percentages): string
    {
        $numbers = $percentages->map(fn (string $value): float => (float) rtrim($value, '%'));

        return $numbers->isEmpty() ? '0%' : number_format($numbers->avg(), 2).'%';
    }

    public function canViewAttempt(ExamAttempt $attempt, User $user): bool
    {
        return $user->isActive() && ($user->hasRole('proctor') || $user->hasRole('instructor'));
    }

    /** @return array<string, mixed> */
    private function summarizeAttempts(Collection $examAttempts, string $name, ?string $subject, mixed $examId): array
    {
        $firstAttempts = $examAttempts->where('attempt_number', 1);
        $retakes = $examAttempts->where('attempt_number', '>', 1);

        return [
            'exam_id' => $examId,
            'name' => $name,
            'subject' => $subject,
            'trainees' => $examAttempts->pluck('student_user_id')->unique()->count(),
            'passed' => $examAttempts->where('passed', true)->count(),
            'failed' => $examAttempts->where('passed', false)->count(),
            'rate' => $this->rate($examAttempts->where('passed', true)->count(), $examAttempts->whereIn('passed', [true, false])->count()),
            'average' => $this->average($examAttempts),
            'retaking' => $retakes->pluck('student_user_id')->unique()->count(),
            'retake_passed' => $retakes->where('passed', true)->count(),
            'retake_failed' => $retakes->where('passed', false)->count(),
            'retake_rate' => $this->rate($retakes->where('passed', true)->count(), $retakes->whereIn('passed', [true, false])->count()),
            'retake_average' => $this->average($retakes),
            'attempts' => $examAttempts->sortBy([['attempt_number', 'asc'], ['submitted_at', 'desc']])->values(),
            'initial_attempts' => $firstAttempts->count(),
        ];
    }

    private function average(Collection $attempts): string
    {
        $scores = $attempts->pluck('score')->filter(fn ($score): bool => $score !== null);

        return $scores->isEmpty() ? '0%' : number_format((float) $scores->avg(), 2).'%';
    }

    private function rate(int $numerator, int $denominator): string
    {
        return $denominator > 0 ? number_format(($numerator / $denominator) * 100, 2).'%' : '0%';
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    public function dateBounds(Request $request): array
    {
        $range = (string) $request->input('date_range', 'All Time');
        if ($range === 'All Time') {
            return [null, null];
        }

        if (preg_match('/Previous (\d+) Month/', $range, $matches)) {
            return [now()->subMonths((int) $matches[1])->startOfDay(), now()->endOfDay()];
        }

        if (preg_match('/Previous (\d+) Year/', $range, $matches)) {
            return [now()->subYears((int) $matches[1])->startOfDay(), now()->endOfDay()];
        }

        return [$this->parseDate($request->input('start_date'), false), $this->parseDate($request->input('end_date'), true)];
    }

    private function parseDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
