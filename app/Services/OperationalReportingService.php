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
                'course',
                'examSchedules.exam.subject',
                'examSchedules.attempts.student.profile',
                'examSchedules.attempts.exam.subject',
            ])
            ->withCount('enrollments')
            ->latest()
            ->get();
    }

    /** @return Collection<int, ExamAttempt> */
    public function filteredAttempts(Collection $classes, Request $request): Collection
    {
        $courseId = $request->integer('course_id') ?: null;
        [$from, $to] = $this->dateBounds($request);

        return $classes
            ->flatMap(function (TrainingClass $trainingClass): Collection {
                return $trainingClass->examSchedules->flatMap(function ($schedule) use ($trainingClass): Collection {
                    return $schedule->attempts->map(function (ExamAttempt $attempt) use ($schedule, $trainingClass): ExamAttempt {
                        return $attempt->setRelation('schedule', $schedule)->setRelation('trainingClass', $trainingClass);
                    });
                });
            })
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
                $firstAttempts = $examAttempts->where('attempt_number', 1);
                $retakes = $examAttempts->where('attempt_number', '>', 1);
                $exam = $examAttempts->first()->exam;

                return [
                    'exam_id' => $exam?->getKey(),
                    'name' => $exam?->name ?: 'Assessment',
                    'subject' => $exam?->subject?->name ?: 'Not assigned',
                    'trainees' => $examAttempts->pluck('student_user_id')->unique()->count(),
                    'passed' => $examAttempts->where('passed', true)->count(),
                    'failed' => $examAttempts->where('passed', false)->count(),
                    'rate' => $this->rate($examAttempts->whereIn('passed', [true, false])->where('passed', true)->count(), $examAttempts->whereIn('passed', [true, false])->count()),
                    'average' => $this->average($examAttempts),
                    'retaking' => $retakes->pluck('student_user_id')->unique()->count(),
                    'retake_passed' => $retakes->where('passed', true)->count(),
                    'retake_failed' => $retakes->where('passed', false)->count(),
                    'retake_rate' => $this->rate($retakes->where('passed', true)->count(), $retakes->whereIn('passed', [true, false])->count()),
                    'retake_average' => $this->average($retakes),
                    'attempts' => $examAttempts->sortBy([['attempt_number', 'asc'], ['submitted_at', 'desc']])->values(),
                    'initial_attempts' => $firstAttempts->count(),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    public function canViewAttempt(ExamAttempt $attempt, User $user): bool
    {
        return $user->isActive() && ($user->hasRole('proctor') || $user->hasRole('instructor'));
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
    private function dateBounds(Request $request): array
    {
        $range = (string) $request->input('date_range', 'Custom Date Range');
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
