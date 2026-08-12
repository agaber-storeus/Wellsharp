<?php

namespace App\Enums;

enum CourseStatus: string
{
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
