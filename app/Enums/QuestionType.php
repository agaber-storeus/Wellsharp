<?php

namespace App\Enums;

enum QuestionType: string
{
    case TrueFalse = 'true_false';
    case Mcq = 'mcq';
    case Input = 'input';

    public function label(): string
    {
        return match ($this) {
            self::TrueFalse => 'True / False',
            self::Mcq => 'Multiple choice',
            self::Input => 'Text input',
        };
    }
}
