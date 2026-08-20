<?php

namespace App\Services\Generation;

/**
 * Shared "generate candidate -> check uniqueness -> retry" loop used by every
 * identifier/code generator in the app. Centralizing it here means collision
 * handling (bounded retries, a meaningful failure instead of an infinite
 * loop) only has to be implemented once.
 */
trait GeneratesUniqueCode
{
    /**
     * @param  callable(int $attempt): string  $candidate  Builds one candidate value. Receives the
     *                                                     zero-based attempt number so callers can widen the
     *                                                     candidate space (e.g. more random digits) on retries.
     * @param  callable(string $value): bool  $exists  Returns true if the candidate is already taken.
     */
    private function generateUnique(callable $candidate, callable $exists, int $maxAttempts = 25): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $value = $candidate($attempt);
            if (! $exists($value)) {
                return $value;
            }
        }

        throw new GenerationRetryExhaustedException(
            static::class." could not generate a unique value after {$maxAttempts} attempts."
        );
    }
}
