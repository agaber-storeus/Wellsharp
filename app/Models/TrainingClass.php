<?php

namespace App\Models;

use App\Enums\ClassStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingClass extends Model
{
    use HasFactory, HasPublicUlid;

    protected $table = 'classes';

    protected $fillable = ['class_number', 'course_id', 'training_provider_id', 'proctor_id', 'instructor_id', 'status', 'starts_at', 'ends_at', 'actual_started_at', 'actual_ended_at', 'notes'];

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

    public function proctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proctor_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
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

    /**
     * Exam and Class are the same operational record; Proctor/Instructor/Student
     * screens should show the Exam's own name as the Class title, not the Subject
     * (Course) it belongs to. Falls back to the Course/class number only for a
     * Class that has no linked Exam schedule yet, which shouldn't normally happen
     * once created through the unified Exam/Class admin workflow.
     */
    public function displayTitle(): string
    {
        $examName = $this->examSchedules->first()?->exam?->name;
        if (filled($examName)) {
            return $examName;
        }

        $course = $this->course;

        return $course ? trim($course->code.' — '.$course->name, ' —') : $this->class_number;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Central ownership scope: Admin sees every Class; a Proctor/Instructor sees
     * only Classes assigned to them. Every Proctor/Instructor-facing dashboard,
     * browse, analytics, and certificate query must go through this instead of
     * re-deriving the ownership filter, so there is exactly one place that
     * decides "which Classes can this user see."
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->hasRole(Role::PROCTOR)) {
            return $query->where('proctor_id', $user->getKey());
        }

        if ($user->hasRole(Role::INSTRUCTOR)) {
            return $query->where('instructor_id', $user->getKey());
        }

        return $query->whereRaw('1 = 0');
    }
}
