<?php

namespace App\Enums;

enum StaffAssignmentRole: string
{
    case Proctor = 'proctor';
    case Instructor = 'instructor';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
