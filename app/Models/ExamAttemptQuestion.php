<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class ExamAttemptQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['exam_attempt_id', 'question_id', 'display_order', 'points', 'answer', 'answered_at', 'option_order'];

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'points' => 'decimal:2', 'answered_at' => 'datetime', 'option_order' => 'array'];
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
}
