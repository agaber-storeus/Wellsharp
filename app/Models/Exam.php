<?php

namespace App\Models;

use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['course_id', 'name', 'code', 'description', 'passing_score', 'retake_score', 'question_order_mode', 'status', 'created_by_user_id', 'updated_by_user_id'];

    protected function casts(): array
    {
        return ['passing_score' => 'integer', 'retake_score' => 'integer', 'question_order_mode' => ExamQuestionOrderMode::class, 'status' => ExamStatus::class];
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
