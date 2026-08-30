<?php

namespace App\Services;

use App\Enums\ProctorVerificationFailureReason;
use App\Models\ExamControlCredential;
use App\Models\Role;
use App\Models\User;
use App\Support\ProctorVerificationResult;

class ProctorIdVerifier
{
    /**
     * Resolve a Proctor's ID to an active Proctor, regardless of who is asking.
     * Used for the Instructor oversight check: the credential must belong to an
     * eligible Proctor, never to the requesting user's own (Instructor) credential.
     */
    public function findActiveProctor(string $controlId): ?User
    {
        return $this->verify($controlId)->proctor;
    }

    /**
     * Same lookup as findActiveProctor(), but keeps the specific reason a
     * failed attempt was rejected - used to build a meaningful Proctor
     * verification audit trail without ever storing the entered credential.
     */
    public function verify(string $controlId): ProctorVerificationResult
    {
        $trimmed = strtoupper(trim($controlId));

        if ($trimmed === '') {
            return ProctorVerificationResult::failure(ProctorVerificationFailureReason::MissingProctorId);
        }

        $credential = ExamControlCredential::query()
            ->where('control_id', $trimmed)
            ->with(['user.profile', 'user.currentRole'])
            ->first();
        $user = $credential?->user;

        if (! $user) {
            return ProctorVerificationResult::failure(ProctorVerificationFailureReason::UserNotFound);
        }

        if ($user->currentRole?->key !== Role::PROCTOR) {
            return ProctorVerificationResult::failure(ProctorVerificationFailureReason::NotAProctor);
        }

        if (! $user->isActive()) {
            return ProctorVerificationResult::failure(ProctorVerificationFailureReason::ProctorInactive);
        }

        return ProctorVerificationResult::success($user);
    }
}
