<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class ProctorIdVerifier
{
    public function findFor(User $user, string $controlId): ?User
    {
        $user->loadMissing(['profile', 'currentRole', 'examControlCredential']);

        if (! $user->isActive() || ! $user->currentRole || ! in_array($user->currentRole->key, [Role::PROCTOR, Role::INSTRUCTOR], true)) {
            return null;
        }

        return $user->examControlCredential?->control_id === strtoupper(trim($controlId)) ? $user : null;
    }
}
