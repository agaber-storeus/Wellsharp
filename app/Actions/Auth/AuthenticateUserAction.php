<?php

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthenticateUserAction
{
    public function execute(string $wellsharpId, string $password, ?string $ip, ?string $userAgent, ?string $correlationId): ?User
    {
        $normalizedId = strtoupper(trim($wellsharpId));
        $user = User::where('wellsharp_id', $normalizedId)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->event($normalizedId, null, 'invalid_credentials', $ip, $userAgent, $correlationId);

            return null;
        }

        if ($user->status !== UserStatus::Active || $user->archived_at !== null) {
            $this->event($normalizedId, $user, 'inactive', $ip, $userAgent, $correlationId);

            return null;
        }

        Auth::login($user);
        $user->forceFill(['last_login_at' => now()])->save();
        $this->event($normalizedId, $user, 'success', $ip, $userAgent, $correlationId);

        return $user->fresh('currentRole');
    }

    private function event(string $wellsharpId, ?User $user, string $outcome, ?string $ip, ?string $userAgent, ?string $correlationId): void
    {
        LoginEvent::create([
            'user_id' => $user?->getKey(),
            'wellsharp_id' => $wellsharpId,
            'outcome' => $outcome,
            'correlation_id' => $correlationId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'occurred_at' => now(),
        ]);
    }
}
