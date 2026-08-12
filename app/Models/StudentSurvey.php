<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentSurvey extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['student_user_id', 'exam_schedule_id', 'status', 'contact_confirmed_at', 'completed_at'];

    protected function casts(): array
    {
        return ['contact_confirmed_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StudentSurveyAnswer::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
