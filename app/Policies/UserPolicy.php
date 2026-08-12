<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isAdmin() ? true : null;
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
