<?php

namespace App\Services\Translation;

use App\Enums\LanguageDirection;

final readonly class SupportedLanguage
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $nativeName,
        public LanguageDirection $direction,
        public array $metadata = [],
    ) {}
}
