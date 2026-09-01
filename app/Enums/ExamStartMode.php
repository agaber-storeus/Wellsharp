<?php

namespace App\Enums;

enum ExamStartMode: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic — follow start/end dates',
            self::Manual => 'Manual — start only by Proctor or Proctor ID',
        };
    }
}
