<?php

namespace App\Models;

use App\Enums\LanguageDirection;
use App\Enums\TranslationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class ExamAttemptQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_attempt_id', 'question_id', 'display_order', 'points', 'answer', 'answered_at', 'option_order',
        'translated_question_text', 'translated_options', 'question_translation_status',
        'back_translated_answer', 'answer_translation_status', 'answer_translation_provider', 'answer_translated_at',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'points' => 'decimal:2',
            'answered_at' => 'datetime',
            'option_order' => 'array',
            'translated_options' => 'array',
            'question_translation_status' => TranslationStatus::class,
            'answer_translation_status' => TranslationStatus::class,
            'answer_translated_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * The question's MCQ options in the order frozen for this attempt, falling back to the
     * question's natural display order when no shuffled order was stored (non-MCQ, static
     * exams, or attempts created before per-student option shuffling existed).
     */
    public function orderedOptions(): Collection
    {
        $options = $this->question->options;

        if (blank($this->option_order)) {
            return $options;
        }

        return collect($this->option_order)
            ->map(fn (string $publicId) => $options->firstWhere('public_id', $publicId))
            ->filter()
            ->values();
    }

    /**
     * The Question text to show this attempt's student: the translated
     * snapshot frozen at attempt-start time, or the original source text
     * when the attempt was started in the original language.
     */
    public function displayQuestionText(): string
    {
        return $this->translated_question_text ?? $this->question->question_text;
    }

    /**
     * The MCQ option text to show this attempt's student, keyed by the
     * option's public_id - same original-vs-translated-snapshot fallback
     * as displayQuestionText(). Option identity/scoring never uses this.
     */
    public function displayOptionText(QuestionOption $option): string
    {
        return $this->translated_options[$option->public_id] ?? $option->option_text;
    }

    /**
     * The reading direction for THIS question's displayed content. Falls back to
     * LTR even on a translated attempt when this specific Question's translation
     * failed and it is still showing source-language text (translation_pending),
     * so a provider outage never renders untranslated English under an RTL layout.
     */
    public function displayDirection(?LanguageDirection $attemptDirection): LanguageDirection
    {
        if ($attemptDirection === null || $this->question_translation_status === TranslationStatus::Failed) {
            return LanguageDirection::Ltr;
        }

        return $attemptDirection;
    }
}
