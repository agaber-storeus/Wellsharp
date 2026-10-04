<?php

namespace App\Services;

use App\Enums\ExamScheduleStatus;
use App\Enums\ExamStartMode;
use App\Models\ExamSchedule;
use Illuminate\Support\Carbon;

class ExamScheduleAvailabilityService
{
    public function startsAt(ExamSchedule $schedule): ?Carbon
    {
        if ($schedule->override_started_at) {
            return $schedule->override_started_at->copy();
        }

        return $this->combineDateAndTime($schedule->start_date, $schedule->start_time, '00:00:00');
    }

    public function endsAt(ExamSchedule $schedule): ?Carbon
    {
        return $schedule->override_ended_at?->copy()
            ?? $this->scheduledEndsAt($schedule);
    }

    public function scheduledEndsAt(ExamSchedule $schedule): ?Carbon
    {
        return $this->combineDateAndTime($schedule->end_date, $schedule->end_time, '23:59:59');
    }

    public function canStudentStart(ExamSchedule $schedule): bool
    {
        return $this->studentStartBlockReason($schedule) === null;
    }

    public function studentStartBlockReason(ExamSchedule $schedule): ?string
    {
        if ($schedule->override_ended_at) {
            return 'This exam schedule has ended.';
        }

        if ($schedule->status !== ExamScheduleStatus::Scheduled) {
            return 'This exam schedule is not available.';
        }

        if ($schedule->start_mode === ExamStartMode::Manual && ! $schedule->override_started_at) {
            return 'A Proctor must start this exam before it can be opened.';
        }

        $startsAt = $this->startsAt($schedule);
        $endsAt = $this->scheduledEndsAt($schedule);
        $now = now(config('app.timezone'));

        if ($startsAt && $startsAt->gt($now)) {
            return 'This exam is available starting '.$this->format($startsAt).'.';
        }

        if ($endsAt && $endsAt->lt($now)) {
            return 'This exam schedule has ended.';
        }

        return null;
    }

    public function isCurrentForLogin(ExamSchedule $schedule): bool
    {
        return $this->canStudentStart($schedule);
    }

    public function isUpcomingForLogin(ExamSchedule $schedule): bool
    {
        if ($schedule->status !== ExamScheduleStatus::Scheduled || $schedule->override_ended_at) {
            return false;
        }

        return $this->startsAt($schedule)?->isFuture() === true;
    }

    public function isPastForLogin(ExamSchedule $schedule): bool
    {
        return $schedule->status === ExamScheduleStatus::Completed
            || (bool) $schedule->override_ended_at
            || $this->scheduledEndsAt($schedule)?->isPast() === true;
    }

    public function format(?Carbon $date): string
    {
        return $date?->timezone(config('app.timezone'))->format('F j, Y g:i A T') ?? 'the scheduled time';
    }

    private function combineDateAndTime(?Carbon $date, ?string $time, string $fallback): ?Carbon
    {
        if (! $date) {
            return null;
        }

        $clock = $time ? substr($time, 0, 8) : $fallback;
        if (strlen($clock) === 5) {
            $clock .= ':00';
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d').' '.$clock, config('app.timezone'));
    }
}
