<?php

namespace App\Enums;

enum ExamScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
