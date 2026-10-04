<?php

namespace App\Models;

use App\Enums\KnowledgeControlType;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttemptScoreControl extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'exam_attempt_id', 'exam_attempt_question_id', 'type', 'numeric_value', 'boolean_value',
        'before_state', 'after_state', 'reason', 'created_by_user_id', 'reverted_at', 'reverted_by_user_id', 'revert_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => KnowledgeControlType::class,
            'numeric_value' => 'decimal:2',
            'boolean_value' => 'boolean',
            'before_state' => 'array',
            'after_state' => 'array',
            'reverted_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo { return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id'); }
    public function attemptQuestion(): BelongsTo { return $this->belongsTo(ExamAttemptQuestion::class, 'exam_attempt_question_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function revertedBy(): BelongsTo { return $this->belongsTo(User::class, 'reverted_by_user_id'); }
    public function getRouteKeyName(): string { return 'public_id'; }
}
