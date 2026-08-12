<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
