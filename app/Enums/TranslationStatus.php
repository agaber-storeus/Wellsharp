<?php

namespace App\Enums;

enum TranslationStatus: string
{
    case Translated = 'translated';
    case Failed = 'failed';

    public function label(): string
    {
        return $this === self::Translated ? 'Translated' : 'Translation failed';
    }
}
