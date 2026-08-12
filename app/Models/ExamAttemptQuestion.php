<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttemptQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['exam_attempt_id', 'question_id', 'display_order', 'points', 'answer', 'answered_at'];

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'points' => 'decimal:2', 'answered_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
