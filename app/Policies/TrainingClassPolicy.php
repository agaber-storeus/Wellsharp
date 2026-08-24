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
            return $this->isAssigned($actor, $trainingClass);
        }

        return $actor->hasRole(Role::STUDENT) && $trainingClass->enrollments()->where('student_user_id', $actor->getKey())->where('status', EnrollmentStatus::Enrolled)->exists();
    }

    /**
     * Only the Class's own assigned Proctor/Instructor may start/end it - this
     * is a class-management action, not a general operational permission, so
     * it follows the assignment (see PROJECT_BRAIN.md §Needs Business
     * Confirmation / class_staff_assignments) rather than "any active staff."
     */
    public function control(User $actor, TrainingClass $trainingClass): bool
    {
        return $this->isAssigned($actor, $trainingClass);
    }

    /**
     * Gates the Class Dashboard's batch Student-password endpoint. Scoped to
     * the Class's own assigned Proctor/Instructor, same as control() - target-side
     * "is this a Student" is enforced per-row in the controller instead, since
     * this ability is checked once per Class, not once per Student.
     */
    public function viewStudentPasswords(User $actor, TrainingClass $trainingClass): bool
    {
        return $actor->isActive() && $this->isAssigned($actor, $trainingClass);
    }

    private function isAssigned(User $actor, TrainingClass $trainingClass): bool
    {
        if ($actor->hasRole(Role::PROCTOR)) {
            return $trainingClass->proctor_id === $actor->getKey();
        }

        if ($actor->hasRole(Role::INSTRUCTOR)) {
            return $trainingClass->instructor_id === $actor->getKey();
        }

        return false;
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
