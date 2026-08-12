<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isAdmin() ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, Exam $exam): bool
    {
        return false;
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Exam $exam): bool
    {
        return false;
    }

    public function delete(User $actor, Exam $exam): bool
    {
        return false;
    }
}
