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
     * Admin, and active Proctor/Instructor, may record a trainee's hands-on
     * Skills Score. Business rule mirrors UserPolicy::viewPassword — staff
     * running the class need to enter this live, not just the class owner.
     */
    public function updateSkillsScore(User $actor, Enrollment $enrollment): bool
    {
        return $actor->isActive() && ($actor->hasRole(Role::PROCTOR) || $actor->hasRole(Role::INSTRUCTOR));
    }
}
