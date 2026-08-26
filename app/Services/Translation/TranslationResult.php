<?php

namespace App\Services\Translation;

/**
 * The outcome of translating a single string. A batch call returns one of
 * these per input so callers can handle a partial batch failure item by
 * item, instead of one bad string failing translation for everything else
 * in the same request.
 */
final readonly class TranslationResult
{
    private function __construct(
        public bool $success,
        public ?string $text,
        public ?string $error,
    ) {}

    public static function success(string $text): self
    {
        return new self(true, $text, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }
}
