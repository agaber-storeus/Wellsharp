<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isAdmin() ? true : null;
    }

    public function view(User $actor, Enrollment $enrollment): bool
    {
        return $actor->getKey() === $enrollment->student_user_id;
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Enrollment $enrollment): bool
    {
        return false;
    }
}
