<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'exam_id', 'exam_schedule_id', 'student_user_id', 'attempt_number', 'status',
        'started_at', 'expires_at', 'submitted_at', 'score', 'passed', 'scored_at', 'released_at', 'released_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => ExamAttemptStatus::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'passed' => 'boolean',
            'scored_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(ExamAttemptQuestion::class)->orderBy('display_order');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
