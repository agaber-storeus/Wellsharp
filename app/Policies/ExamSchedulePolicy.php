<?php

namespace App\Policies;

use App\Models\ExamSchedule;
use App\Models\User;

class ExamSchedulePolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isAdmin() ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, ExamSchedule $schedule): bool
    {
        return false;
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, ExamSchedule $schedule): bool
    {
        return false;
    }

    public function delete(User $actor, ExamSchedule $schedule): bool
    {
        return false;
    }
}
