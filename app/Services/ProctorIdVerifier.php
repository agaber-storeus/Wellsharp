<?php

namespace App\Services;

use App\Models\ExamControlCredential;
use App\Models\Role;
use App\Models\User;

class ProctorIdVerifier
{
    /**
     * Resolve a Proctor's ID to an active Proctor, regardless of who is asking.
     * Used for the Instructor oversight check: the credential must belong to an
     * eligible Proctor, never to the requesting user's own (Instructor) credential.
     */
    public function findActiveProctor(string $controlId): ?User
    {
        $credential = ExamControlCredential::query()
            ->where('control_id', strtoupper(trim($controlId)))
            ->with(['user.profile', 'user.currentRole'])
            ->first();
        $user = $credential?->user;

        if (! $user || ! $user->isActive() || $user->currentRole?->key !== Role::PROCTOR) {
            return null;
        }

        return $user;
    }
}
