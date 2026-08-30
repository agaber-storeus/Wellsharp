<?php

namespace App\Support;

use App\Enums\ProctorVerificationFailureReason;
use App\Models\User;

final class ProctorVerificationResult
{
    private function __construct(
        public readonly ?User $proctor,
        public readonly ?ProctorVerificationFailureReason $failureReason,
    ) {}

    public static function success(User $proctor): self
    {
        return new self($proctor, null);
    }

    public static function failure(ProctorVerificationFailureReason $reason): self
    {
        return new self(null, $reason);
    }

    public function succeeded(): bool
    {
        return $this->proctor !== null;
    }
}
