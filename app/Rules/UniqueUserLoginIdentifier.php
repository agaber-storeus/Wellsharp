<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueUserLoginIdentifier implements ValidationRule
{
    public function __construct(private readonly ?User $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = strtolower(trim((string) $value));

        $exists = User::query()
            ->when($this->ignore, fn ($query) => $query->whereKeyNot($this->ignore->getKey()))
            ->where(function ($query) use ($normalized): void {
                $query->whereRaw('LOWER(wellsharp_id) = ?', [$normalized])
                    ->orWhereRaw('LOWER(username) = ?', [$normalized]);
            })
            ->exists();

        if ($exists) {
            $fail('The :attribute has already been taken.');
        }
    }
}
