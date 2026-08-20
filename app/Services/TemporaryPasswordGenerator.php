<?php

namespace App\Services;

use App\Models\Role;

/**
 * Generates temporary plaintext passwords for new/reset accounts using
 * cryptographically secure randomness (random_int).
 *
 * Business rule: generated passwords must be exactly within 5-8 characters
 * (default 8) for Proctor/Instructor/Admin, and exactly 5 characters for
 * Student - see Role::passwordLengthRules() for the matching validation
 * policy applied in StoreUserRequest/UpdateUserRequest. UpdateProfileRequest
 * and the wellsharp:create-admin console command are role-fixed to
 * Proctor/Instructor and Admin respectively, so they never need the
 * Student-specific length and keep the plain 5-8 rule directly.
 *
 * The result is only ever meant to be hashed immediately and shown to an
 * Admin once (never logged, never persisted in plaintext).
 */
class TemporaryPasswordGenerator
{
    private const MIN_LENGTH = 5;

    private const MAX_LENGTH = 8;

    private const DEFAULT_LENGTH = 8;

    private const STUDENT_LENGTH = 5;

    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghjkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    /**
     * Role-aware entry point: Students always get an exact 5-character
     * password, every other role gets the general policy (default 8).
     */
    public function generateForRole(?string $roleKey): string
    {
        return $this->generate($roleKey === Role::STUDENT ? self::STUDENT_LENGTH : self::DEFAULT_LENGTH);
    }

    public function generate(int $length = self::DEFAULT_LENGTH): string
    {
        $length = min(self::MAX_LENGTH, max(self::MIN_LENGTH, $length));
        $pools = [self::UPPER, self::LOWER, self::DIGITS];

        // Guarantee at least one character from each pool, then fill the
        // remaining length from the combined pool for unpredictability.
        $chars = array_map(fn (string $pool): string => $pool[random_int(0, strlen($pool) - 1)], $pools);
        $combined = implode('', $pools);
        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $combined[random_int(0, strlen($combined) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
