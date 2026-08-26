<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOptionTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_option_id', 'translation_language_id', 'source_hash', 'translated_text', 'provider', 'translated_at',
    ];

    protected function casts(): array
    {
        return ['translated_at' => 'datetime'];
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'question_option_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(TranslationLanguage::class, 'translation_language_id');
    }
}
