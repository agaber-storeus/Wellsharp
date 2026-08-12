<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;

class AuditPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->isAdmin() ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, AuditEvent $event): bool
    {
        return false;
    }
}
