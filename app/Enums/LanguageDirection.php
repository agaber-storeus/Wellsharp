<?php

namespace App\Enums;

enum LanguageDirection: string
{
    case Ltr = 'ltr';
    case Rtl = 'rtl';

    public function label(): string
    {
        return $this === self::Rtl ? 'Right to left' : 'Left to right';
    }
}
