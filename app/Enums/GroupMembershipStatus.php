<?php

namespace App\Enums;

enum GroupMembershipStatus: string
{
    case Active = 'active';
    case Removed = 'removed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
