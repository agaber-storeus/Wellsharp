<?php

namespace App\Services\Translation;

use App\Models\TranslationLanguage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Refreshes the local translation_languages catalog from the provider's
 * dynamic language list (never hardcoded - see TranslationProviderInterface
 * ::getSupportedLanguages()). A newly-discovered language is inserted
 * disabled by default (Admin must opt in); a previously-synced language's
 * is_enabled flag is never touched here - only Admin's own save toggles it.
 */
class TranslationLanguageSyncService
{
    public function __construct(private readonly TranslationProviderInterface $provider) {}

    /**
     * @return Collection<int, TranslationLanguage>
     *
     * @throws TranslationProviderException if the provider is unreachable.
     */
    public function sync(): Collection
    {
        $languages = $this->provider->getSupportedLanguages();
        $syncedAt = now();

        return DB::transaction(function () use ($languages, $syncedAt): Collection {
            foreach ($languages as $language) {
                TranslationLanguage::query()->updateOrCreate(
                    ['provider' => $this->provider->name(), 'code' => $language->code],
                    [
                        'name' => $language->name,
                        'native_name' => $language->nativeName,
                        'direction' => $language->direction,
                        'provider_metadata' => $language->metadata,
                        'last_synced_at' => $syncedAt,
                    ],
                );
            }

            return TranslationLanguage::query()
                ->where('provider', $this->provider->name())
                ->orderBy('name')
                ->get();
        });
    }
}
