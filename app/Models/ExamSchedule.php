<?php

namespace App\Models;

use App\Enums\ExamScheduleStatus;
use App\Enums\ExamStartMode;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSchedule extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['class_id', 'stack_offered', 'supplement_offered', 'exam_id', 'group_id', 'training_class_id', 'training_provider_id', 'training_provider_location_id', 'start_date', 'start_time', 'end_date', 'end_time', 'duration_minutes', 'status', 'start_mode', 'created_by_user_id', 'updated_by_user_id', 'override_started_at', 'override_ended_at', 'override_started_by_user_id', 'override_ended_by_user_id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'duration_minutes' => 'integer', 'status' => ExamScheduleStatus::class, 'start_mode' => ExamStartMode::class, 'override_started_at' => 'datetime', 'override_ended_at' => 'datetime'];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function trainingClass(): BelongsTo
    {
        return $this->belongsTo(TrainingClass::class, 'training_class_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TrainingProvider::class, 'training_provider_id');
    }

    public function providerLocation(): BelongsTo
    {
        return $this->belongsTo(TrainingProviderLocation::class, 'training_provider_location_id');
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
