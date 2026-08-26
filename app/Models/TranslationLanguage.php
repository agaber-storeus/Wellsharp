<?php

namespace App\Models;

use App\Enums\LanguageDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranslationLanguage extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider', 'code', 'name', 'native_name', 'direction',
        'is_enabled', 'provider_metadata', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => LanguageDirection::class,
            'is_enabled' => 'boolean',
            'provider_metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function questionTranslations(): HasMany
    {
        return $this->hasMany(QuestionTranslation::class);
    }

    public function optionTranslations(): HasMany
    {
        return $this->hasMany(QuestionOptionTranslation::class);
    }
}
