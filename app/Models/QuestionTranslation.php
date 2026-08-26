<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id', 'translation_language_id', 'source_hash', 'translated_text', 'provider', 'translated_at',
    ];

    protected function casts(): array
    {
        return ['translated_at' => 'datetime'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(TranslationLanguage::class, 'translation_language_id');
    }

    public function isStale(): bool
    {
        return $this->source_hash !== $this->question->question_text_hash;
    }
}
