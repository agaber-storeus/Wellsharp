<?php

namespace App\Services\Translation;

/**
 * Business logic (QuestionTranslationService, admin language sync, Text
 * Input back-translation) depends only on this contract - never on a
 * specific provider's HTTP payload shape. LibreTranslateProvider is the
 * only implementation today; a future provider (Azure/Google/etc.) is a
 * new class behind this same interface, with no caller changes required.
 */
interface TranslationProviderInterface
{
    public function name(): string;

    /**
     * @return SupportedLanguage[] keyed by nothing in particular - callers key by ->code themselves.
     *
     * @throws TranslationProviderException if the provider cannot be reached or the response is unusable.
     */
    public function getSupportedLanguages(): array;

    /**
     * Cheap reachability check for the Admin "Provider Status" indicator.
     * Never throws - returns false for any failure.
     */
    public function isAvailable(): bool;

    /**
     * Translates every string in $texts from $sourceLanguageCode to
     * $targetLanguageCode, batched in as few provider requests as the
     * provider's own limits allow. Always returns exactly count($texts)
     * results, in the same order, one per input - a failure for one item
     * (unsupported language, empty provider response, etc.) is reported on
     * that item's TranslationResult, not by throwing and failing the batch.
     *
     * @param  string[]  $texts
     * @return TranslationResult[]
     *
     * @throws TranslationProviderException if the provider is entirely unreachable/misconfigured for this call.
     */
    public function translate(array $texts, string $sourceLanguageCode, string $targetLanguageCode): array;
}
