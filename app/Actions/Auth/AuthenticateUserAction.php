<?php

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\LoginEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\StudentLoginEligibilityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateUserAction
{
    public function __construct(private readonly StudentLoginEligibilityService $studentLoginEligibility) {}

    public function execute(string $identifier, string $password, ?string $ip, ?string $userAgent, ?string $correlationId): ?User
    {
        $submittedIdentifier = trim($identifier);
        $normalizedIdentifier = strtolower($submittedIdentifier);
        $matches = User::query()
            ->whereRaw('LOWER(wellsharp_id) = ?', [$normalizedIdentifier])
            ->orWhereRaw('LOWER(username) = ?', [$normalizedIdentifier])
            ->limit(2)
            ->get();
        $user = $matches->count() === 1 ? $matches->first() : null;
        $eventIdentifier = $user?->wellsharp_id ?? strtoupper($submittedIdentifier);

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->event($eventIdentifier, null, 'invalid_credentials', $ip, $userAgent, $correlationId);

            return null;
        }

        if ($user->status !== UserStatus::Active || $user->archived_at !== null) {
            $this->event($eventIdentifier, $user, 'inactive', $ip, $userAgent, $correlationId);

            return null;
        }

        $user->loadMissing('currentRole');
        if ($user->currentRole?->key === Role::STUDENT && $message = $this->studentLoginEligibility->denialMessageFor($user)) {
            $this->event($eventIdentifier, $user, 'student_exam_unavailable', $ip, $userAgent, $correlationId);

            throw ValidationException::withMessages(['wellsharp_id' => $message]);
        }

        Auth::login($user);
        $user->forceFill(['last_login_at' => now()])->save();
        $this->event($eventIdentifier, $user, 'success', $ip, $userAgent, $correlationId);

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
