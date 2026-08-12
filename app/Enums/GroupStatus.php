<?php

namespace App\Enums;

enum GroupStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
