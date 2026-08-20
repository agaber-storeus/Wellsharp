<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditRecorder
{
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?int $actorId = null,
    ): AuditEvent {
        $request = app()->bound('request') ? request() : null;

        return AuditEvent::create([
            'actor_user_id' => $actorId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'before_state' => $this->safeState($before),
            'after_state' => $this->safeState($after),
            'reason' => $reason,
            'correlation_id' => $request?->attributes->get('correlation_id'),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'occurred_at' => now(),
        ]);
    }

    private function safeState(?array $state): ?array
    {
        if ($state === null) {
            return null;
        }

        return Arr::except($state, ['password', 'password_confirmation', 'password_ciphertext', 'remember_token', 'correct_answer', 'answer_key', 'secret', 'token']);
    }
}
