<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Role;
use App\Models\TrainingClass;
use App\Models\User;

class TrainingClassPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isAdmin() ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Role::PROCTOR) || $actor->hasRole(Role::INSTRUCTOR) || $actor->hasRole(Role::STUDENT);
    }

    public function view(User $actor, TrainingClass $trainingClass): bool
    {
        if ($actor->hasRole(Role::PROCTOR) || $actor->hasRole(Role::INSTRUCTOR)) {
            return true;
        }

        return $actor->hasRole(Role::STUDENT) && $trainingClass->enrollments()->where('student_user_id', $actor->getKey())->where('status', EnrollmentStatus::Enrolled)->exists();
    }

    public function control(User $actor, TrainingClass $trainingClass): bool
    {
        return $actor->hasRole(Role::PROCTOR) || $actor->hasRole(Role::INSTRUCTOR);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, TrainingClass $trainingClass): bool
    {
        return false;
    }

    public function delete(User $actor, TrainingClass $trainingClass): bool
    {
        return false;
    }
}
