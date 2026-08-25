<?php

namespace App\Enums;

enum ExamQuestionSelectionMode: string
{
    case Manual = 'manual';
    case Random = 'random';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual question selection',
            self::Random => 'Random questions per student',
        };
    }
}
