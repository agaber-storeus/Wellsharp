<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Confirms a submitted user id resolves to a currently active user whose
 * current role matches the given role key (e.g. Proctor or Instructor).
 * Centralizes the "don't trust a manipulated frontend request" check shared
 * by every Class/Exam-Schedule creation surface, instead of duplicating the
 * exists+role+active checks in each Form Request.
 */
class ActiveStaffWithRole implements ValidationRule
{
    public function __construct(private readonly string $roleKey, private readonly string $label) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $user = User::query()->with('currentRole')->find($value);

        if (! $user || $user->currentRole?->key !== $this->roleKey || ! $user->isActive()) {
            $fail("Select an active {$this->label}.");
        }
    }
}
