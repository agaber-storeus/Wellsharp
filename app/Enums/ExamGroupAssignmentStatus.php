<?php

namespace App\Enums;

enum ExamGroupAssignmentStatus: string
{
    case Active = 'active';
    case Removed = 'removed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
