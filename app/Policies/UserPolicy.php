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
     * Admins may look up any account password for account management. Active
     * Proctors/Instructors are restricted to Student targets.
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
