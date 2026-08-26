<?php

namespace App\Services\Translation\Providers;

use App\Enums\LanguageDirection;
use App\Services\Translation\SupportedLanguage;
use App\Services\Translation\TranslationProviderException;
use App\Services\Translation\TranslationProviderInterface;
use App\Services\Translation\TranslationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only implemented TranslationProviderInterface today. Owns everything
 * specific to talking to a LibreTranslate instance: HTTP calls, request/
 * response shape, batching, and mapping provider errors into the generic
 * TranslationResult/TranslationProviderException contract. No exam/Question/
 * Admin concept is known here - see TranslationProviderInterface.
 */
class LibreTranslateProvider implements TranslationProviderInterface
{
    /**
     * LibreTranslate has no language-direction concept in its API response;
     * direction is inherent to the language itself, not provider-specific,
     * so it is resolved from this small curated list rather than invented
     * per provider. Extend this list, not the interface, if a right-to-left
     * language is missing.
     */
    private const RTL_LANGUAGE_CODES = ['ar', 'he', 'fa', 'ur', 'yi', 'ku', 'ps', 'sd', 'dv'];

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeoutSeconds,
    ) {}

    public function name(): string
    {
        return 'libretranslate';
    }

    public function getSupportedLanguages(): array
    {
        try {
            $response = $this->client()->get('/languages');
        } catch (Throwable $e) {
            throw new TranslationProviderException('LibreTranslate is unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new TranslationProviderException("LibreTranslate /languages returned HTTP {$response->status()}.");
        }

        $languages = $response->json();
        if (! is_array($languages)) {
            throw new TranslationProviderException('LibreTranslate /languages returned an unusable response.');
        }

        return collect($languages)
            ->filter(fn ($entry): bool => is_array($entry) && filled($entry['code'] ?? null) && filled($entry['name'] ?? null))
            ->map(fn (array $entry): SupportedLanguage => new SupportedLanguage(
                code: (string) $entry['code'],
                name: (string) $entry['name'],
                nativeName: null,
                direction: in_array($entry['code'], self::RTL_LANGUAGE_CODES, true) ? LanguageDirection::Rtl : LanguageDirection::Ltr,
                metadata: ['targets' => $entry['targets'] ?? []],
            ))
            ->values()
            ->all();
    }

    public function isAvailable(): bool
    {
        try {
            return $this->client()->timeout(min($this->timeoutSeconds, 5))->get('/languages')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function translate(array $texts, string $sourceLanguageCode, string $targetLanguageCode): array
    {
        if ($texts === []) {
            return [];
        }

        $texts = array_values($texts);

        try {
            $response = $this->client()->post('/translate', array_filter([
                'q' => $texts,
                'source' => $sourceLanguageCode,
                'target' => $targetLanguageCode,
                'format' => 'text',
                'api_key' => $this->apiKey,
            ], fn ($value): bool => $value !== null));
        } catch (Throwable $e) {
            throw new TranslationProviderException('LibreTranslate /translate is unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            Log::warning('translation.libretranslate.batch_failed', [
                'status' => $response->status(),
                'target' => $targetLanguageCode,
                'count' => count($texts),
            ]);

            return array_fill(0, count($texts), TranslationResult::failure("LibreTranslate returned HTTP {$response->status()}."));
        }

        $translated = $response->json('translatedText');
        // LibreTranslate returns a bare string, not an array, when it does not support
        // batched `q` arrays (older/self-hosted builds) - normalize a single-item batch.
        if (is_string($translated) && count($texts) === 1) {
            $translated = [$translated];
        }

        if (! is_array($translated) || count($translated) !== count($texts)) {
            Log::warning('translation.libretranslate.malformed_batch_response', [
                'target' => $targetLanguageCode,
                'expected' => count($texts),
                'received' => is_array($translated) ? count($translated) : gettype($translated),
            ]);

            return array_fill(0, count($texts), TranslationResult::failure('LibreTranslate returned a malformed batch response.'));
        }

        return array_map(
            fn ($item): TranslationResult => is_string($item) && $item !== ''
                ? TranslationResult::success($item)
                : TranslationResult::failure('LibreTranslate returned an empty translation.'),
            array_values($translated),
        );
    }

    private function client()
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson();
    }
}
