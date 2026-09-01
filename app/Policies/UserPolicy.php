<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isAdmin() ? true : null;
    }

    /**
     * Admin, and active Proctor/Instructor, may look up a Student's password.
     * Business rule: Student accounts are shared/managed by staff, and Students
     * cannot reset their own password, so staff need a way to hand it to them.
     * Restricted to Student targets only — staff accounts are never revealable.
     */
    public function viewPassword(User $actor, User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($target->currentRole?->key !== Role::STUDENT) {
            return false;
        }

        return $actor->isActive() && ($actor->hasRole(Role::PROCTOR) || $actor->hasRole(Role::INSTRUCTOR));
    }

    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, User $user): bool
    {
        return false;
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, User $user): bool
    {
        return false;
    }

    public function delete(User $actor, User $user): bool
    {
        return false;
    }
}
