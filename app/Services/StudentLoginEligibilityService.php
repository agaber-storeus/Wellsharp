<?php

namespace App\Services;

use App\Enums\GroupMembershipStatus;
use App\Models\ExamSchedule;
use App\Models\User;

class StudentLoginEligibilityService
{
    public function __construct(private readonly ExamScheduleAvailabilityService $availability) {}

    public function denialMessageFor(User $student): ?string
    {
        $schedules = ExamSchedule::query()
            ->whereHas('group.memberships', fn ($query) => $query
                ->where('student_user_id', $student->getKey())
                ->where('status', GroupMembershipStatus::Active->value)
            )
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get(['id', 'group_id', 'start_date', 'start_time', 'end_date', 'end_time', 'status', 'start_mode', 'override_started_at', 'override_ended_at']);

        if ($schedules->isEmpty()) {
            return 'You do not have any scheduled exams at this time.';
        }

        if ($schedules->contains(fn (ExamSchedule $schedule): bool => $this->availability->isCurrentForLogin($schedule))) {
            return null;
        }

        $upcoming = $schedules
            ->filter(fn (ExamSchedule $schedule): bool => $this->availability->isUpcomingForLogin($schedule))
            ->sortBy(fn (ExamSchedule $schedule): array => [$this->availability->startsAt($schedule)?->timestamp ?? PHP_INT_MAX, $schedule->getKey()])
            ->first();

        if ($upcoming) {
            return 'Your next exam is scheduled for '.$this->availability->format($this->availability->startsAt($upcoming)).'. Please log in at the exam time.';
        }

        $past = $schedules
            ->filter(fn (ExamSchedule $schedule): bool => $this->availability->isPastForLogin($schedule))
            ->sortByDesc(fn (ExamSchedule $schedule): array => [$this->availability->endsAt($schedule)?->timestamp ?? 0, $schedule->getKey()])
            ->first();

        if ($past) {
            return 'Your exam ended on '.$this->availability->format($this->availability->endsAt($past)).'.';
        }

        return 'You do not have any scheduled exams at this time.';
    }
}
