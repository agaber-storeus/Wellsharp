<?php

namespace App\Policies;

use App\Models\TrainingProvider;
use App\Models\User;

class TrainingProviderPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isAdmin() ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, TrainingProvider $provider): bool
    {
        return false;
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, TrainingProvider $provider): bool
    {
        return false;
    }

    public function delete(User $actor, TrainingProvider $provider): bool
    {
        return false;
    }
}
