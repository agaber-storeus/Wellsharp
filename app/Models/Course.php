<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['code', 'name', 'description', 'course_level_id', 'status'];

    protected function casts(): array
    {
        return ['status' => CourseStatus::class, 'archived_at' => 'datetime'];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class, 'course_level_id');
    }

    public function stacks(): BelongsToMany
    {
        return $this->belongsToMany(Stack::class, 'course_stack');
    }

    public function supplements(): BelongsToMany
    {
        return $this->belongsToMany(Supplement::class, 'course_supplement');
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'course_language');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(TrainingClass::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
