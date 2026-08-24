<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\Role;
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

    /**
     * A trainee's hands-on Skills Score may only be recorded by the Class's own
     * assigned Proctor/Instructor — this is a class-management action tied to
     * the enrollment's Class, not a general "any active staff" permission.
     */
    public function updateSkillsScore(User $actor, Enrollment $enrollment): bool
    {
        if (! $actor->isActive()) {
            return false;
        }

        $trainingClass = $enrollment->trainingClass;
        if (! $trainingClass) {
            return false;
        }

        if ($actor->hasRole(Role::PROCTOR)) {
            return $trainingClass->proctor_id === $actor->getKey();
        }

        if ($actor->hasRole(Role::INSTRUCTOR)) {
            return $trainingClass->instructor_id === $actor->getKey();
        }

        return false;
    }
}
