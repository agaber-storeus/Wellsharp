<?php

namespace App\Services;

use App\Models\ExamControlCredential;
use App\Models\Role;
use App\Services\Generation\GeneratesUniqueCode;

/**
 * A Proctor's ID is a role-specific exam-control credential, distinct from
 * the account-level WellSharp ID/Username (see UserIdentityGenerator). It
 * shares the same collision-safe generation architecture (GeneratesUniqueCode)
 * as the other short identifiers, but is never merged with them.
 */
class ProctorIdGenerator
{
    use GeneratesUniqueCode;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * A Proctor's ID is owned exclusively by the Proctor role. No other role
     * (Instructor included) is ever eligible to receive one. Callers that
     * create/change a user's role must gate generation through this check
     * rather than re-deriving their own eligible-role list.
     */
    public function eligibleForRole(?string $roleKey): bool
    {
        return $roleKey === Role::PROCTOR;
    }

    public function generate(): string
    {
        return $this->generateUnique(
            fn (): string => 'P'.$this->randomChars(random_int(5, 8) - 1),
            fn (string $candidate): bool => ExamControlCredential::query()->where('control_id', $candidate)->exists(),
        );
    }

    private function randomChars(int $count): string
    {
        $alphabet = self::ALPHABET;
        $chars = '';
        for ($i = 0; $i < $count; $i++) {
            $chars .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $chars;
    }
}
