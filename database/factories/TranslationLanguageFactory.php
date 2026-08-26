<?php

namespace Database\Factories;

use App\Enums\LanguageDirection;
use App\Models\TranslationLanguage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranslationLanguage>
 */
class TranslationLanguageFactory extends Factory
{
    protected $model = TranslationLanguage::class;

    public function definition(): array
    {
        return [
            'provider' => 'libretranslate',
            'code' => fake()->unique()->languageCode(),
            'name' => fake()->unique()->word(),
            'native_name' => null,
            'direction' => LanguageDirection::Ltr,
            'is_enabled' => false,
            'provider_metadata' => null,
            'last_synced_at' => now(),
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => true]);
    }

    public function arabic(): static
    {
        return $this->state(fn (): array => [
            'code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => LanguageDirection::Rtl,
        ]);
    }
}
