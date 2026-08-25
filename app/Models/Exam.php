<?php

namespace App\Models;

use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamQuestionSelectionMode;
use App\Enums\ExamStatus;
use App\Models\Concerns\HasPublicUlid;
use App\Services\ExamCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['course_id', 'name', 'code', 'description', 'passing_score', 'retake_score', 'certificate_validity_years', 'question_order_mode', 'question_selection_mode', 'question_count', 'status', 'created_by_user_id', 'updated_by_user_id'];

    /**
     * Exam codes are generated once on creation, never touched on update, so
     * they stay stable for anything that already references them. Only fills
     * in a code when one wasn't supplied (Admin/API callers may still set
     * their own, matching the existing optional `code` field).
     */
    protected static function booted(): void
    {
        static::creating(function (Exam $exam): void {
            if (blank($exam->code)) {
                $exam->code = app(ExamCodeGenerator::class)->generate();
            }
        });
    }

    protected function casts(): array
    {
        return ['passing_score' => 'integer', 'retake_score' => 'integer', 'certificate_validity_years' => 'integer', 'question_order_mode' => ExamQuestionOrderMode::class, 'question_selection_mode' => ExamQuestionSelectionMode::class, 'question_count' => 'integer', 'status' => ExamStatus::class];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function course(): BelongsTo
    {
        return $this->subject();
    }

    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('display_order');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot(['display_order', 'points'])
            ->orderBy('exam_questions.display_order');
    }

    public function groupAssignments(): HasMany
    {
        return $this->hasMany(ExamGroupAssignment::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'exam_group_assignments')
            ->wherePivot('status', 'active')
            ->withPivot(['public_id', 'status', 'assigned_at', 'removed_at']);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class)->latest('start_date');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
