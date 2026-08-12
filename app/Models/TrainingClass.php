<?php

namespace App\Models;

use App\Enums\ClassStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingClass extends Model
{
    use HasFactory, HasPublicUlid;

    protected $table = 'classes';

    protected $fillable = ['class_number', 'course_id', 'training_provider_id', 'status', 'starts_at', 'ends_at', 'actual_started_at', 'actual_ended_at', 'notes'];

    protected function casts(): array
    {
        return ['status' => ClassStatus::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'actual_started_at' => 'datetime', 'actual_ended_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TrainingProvider::class, 'training_provider_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(ClassStaffAssignment::class, 'class_id');
    }

    public function examSchedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class, 'training_class_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
