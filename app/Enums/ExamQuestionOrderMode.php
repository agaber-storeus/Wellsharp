<?php

namespace App\Enums;

enum ExamQuestionOrderMode: string
{
    case Static = 'static';
    case Shuffle = 'shuffle';

    public function label(): string
    {
        return $this === self::Static ? 'Static order' : 'Shuffle per student';
    }
}
