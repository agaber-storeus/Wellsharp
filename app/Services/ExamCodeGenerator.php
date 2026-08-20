<?php

namespace App\Services;

use App\Models\Exam;
use App\Services\Generation\GeneratesUniqueCode;

/**
 * Generates the Exam.code identifier: 5-8 uppercase alphanumeric characters,
 * unique across exams. Invoked from Exam's model "creating" hook so every
 * creation path (Admin UI, seeders, direct Eloquent create in tests) gets a
 * code without depending on the Blade form.
 */
class ExamCodeGenerator
{
    use GeneratesUniqueCode;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(): string
    {
        return $this->generateUnique(
            fn (): string => $this->randomCode(random_int(5, 8)),
            fn (string $candidate): bool => Exam::query()->where('code', $candidate)->exists(),
        );
    }

    private function randomCode(int $length): string
    {
        $alphabet = self::ALPHABET;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
