<?php

namespace App\Services;

use App\Models\User;
use App\Services\Generation\GeneratesUniqueCode;
use Illuminate\Support\Str;

/**
 * Generates the two name-derived, human-facing identifiers a User gets on
 * creation: the WellSharp ID (uppercase initials + random digits, used to
 * log in) and the Username (lowercase letters, more readable, display-only). Both
 * are distinct from a Proctor's ID (see ProctorIdGenerator), which is a
 * role-specific exam-control credential, not an account identifier.
 */
class UserIdentityGenerator
{
    use GeneratesUniqueCode;

    private const MIN_LENGTH = 5;

    private const MAX_LENGTH = 8;

    public function generateWellsharpId(string $firstName, string $lastName): string
    {
        [$first, $last] = $this->normalizeNameParts($firstName, $lastName);
        $prefix = $this->initialsPrefix($first, $last);

        return $this->generateUnique(
            fn (): string => $prefix.$this->randomDigits(random_int(self::MIN_LENGTH, self::MAX_LENGTH) - strlen($prefix)),
            fn (string $candidate): bool => User::query()->where('wellsharp_id', $candidate)->exists(),
        );
    }

    public function generateUsername(string $firstName, string $lastName): string
    {
        [$first, $last] = $this->normalizeNameParts($firstName, $lastName);
        $base = $this->usernameBase($first, $last);
        $nameLetters = Str::lower($first.$last);
        $nameLetters = $nameLetters !== '' ? $nameLetters : Str::lower($this->randomLetters(8));

        return $this->generateUnique(function (int $attempt) use ($base, $first, $last, $nameLetters): string {
            if ($attempt === 0) {
                return $this->padToMinLength($base, $nameLetters);
            }

            // Keep collisions readable and name-derived. The alternate form
            // starts with the last-name initial and the first name; subsequent
            // attempts retain a name prefix and add random letters only.
            $alternate = Str::lower(
                ($last !== '' ? substr($last, 0, 1) : '') .
                ($first !== '' ? $first : $last)
            );
            $prefix = $attempt === 1 ? ($alternate !== '' ? $alternate : $base) : $base;
            $prefix = substr($prefix, 0, max(1, self::MAX_LENGTH - 3));

            return Str::lower(substr($prefix.$this->randomLetters(3), 0, self::MAX_LENGTH));
        }, fn (string $candidate): bool => User::query()->where('username', $candidate)->exists());
    }

    /**
     * @return array{0: string, 1: string} ASCII-letters-only [first, last], each possibly empty
     *                                     when the source name has no Latin-transliterable characters
     *                                     (e.g. Arabic script), so callers can fall back safely.
     */
    private function normalizeNameParts(string $firstName, string $lastName): array
    {
        $clean = fn (string $value): string => Str::upper((string) preg_replace('/[^A-Za-z]/', '', Str::ascii(trim($value))));

        return [$clean($firstName), $clean($lastName)];
    }

    private function initialsPrefix(string $first, string $last): string
    {
        if ($first === '' && $last === '') {
            // Non-Latin name (e.g. Arabic) with nothing to transliterate:
            // fall back to a random letter pair rather than failing creation.
            return $this->randomLetters(2);
        }

        $firstInitial = $first !== '' ? substr($first, 0, 1) : $this->randomLetters(1);
        $lastInitial = $last !== '' ? substr($last, 0, 1) : $this->randomLetters(1);

        return $firstInitial.$lastInitial;
    }

    private function usernameBase(string $first, string $last): string
    {
        if ($first === '' && $last === '') {
            return Str::lower($this->randomLetters(3));
        }

        $base = Str::lower(($first !== '' ? substr($first, 0, 1) : '').$last);
        if ($base === '' || strlen($base) < 2) {
            $base .= Str::lower($this->randomLetters(2));
        }

        return substr($base, 0, self::MAX_LENGTH);
    }

    private function padToMinLength(string $base, string $nameLetters): string
    {
        $poolLength = strlen($nameLetters);
        $poolIndex = 0;
        while (strlen($base) < self::MIN_LENGTH) {
            $base .= $nameLetters[$poolIndex % $poolLength];
            $poolIndex++;
        }

        return substr($base, 0, self::MAX_LENGTH);
    }

    private function randomDigits(int $count): string
    {
        $count = max(3, $count);
        $digits = '';
        for ($i = 0; $i < $count; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return $digits;
    }

    private function randomLetters(int $count): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $letters = '';
        for ($i = 0; $i < $count; $i++) {
            $letters .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $letters;
    }
}
