<?php

namespace App\Actions\Users;

use App\Models\User;

/**
 * Carries the plaintext temporary password alongside the created User,
 * without ever attaching it to the model (User::$hidden hides `password`,
 * but toArray()/audit logging must never see plaintext, hidden or not).
 * The controller reads generatedPassword once (session-flash it for the
 * "show once" Admin UI) and lets it go out of scope.
 */
final class CreateUserResult
{
    public function __construct(
        public readonly User $user,
        public readonly ?string $generatedPassword,
    ) {}
}
