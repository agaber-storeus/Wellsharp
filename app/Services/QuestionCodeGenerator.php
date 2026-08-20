<?php

namespace App\Services;

use App\Models\Question;
use App\Services\Generation\GeneratesUniqueCode;

/**
 * Generates the Question.code identifier: exactly 5 uppercase alphanumeric
 * characters, unique globally (not scoped per-exam/course - no business rule
 * requires only local uniqueness, and a global code is simpler to audit and
 * reference). Invoked from Question's model "creating" hook so every
 * creation path gets a code without depending on the Blade form.
 */
class QuestionCodeGenerator
{
    use GeneratesUniqueCode;

    private const LENGTH = 5;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(): string
    {
        return $this->generateUnique(
            fn (): string => $this->randomCode(),
            fn (string $candidate): bool => Question::query()->where('code', $candidate)->exists(),
        );
    }

    private function randomCode(): string
    {
        $alphabet = self::ALPHABET;
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
