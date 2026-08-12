<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['class_id', 'student_user_id', 'status', 'enrolled_at', 'withdrawn_at'];

    protected function casts(): array
    {
        return ['status' => EnrollmentStatus::class, 'enrolled_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function trainingClass(): BelongsTo
    {
        return $this->belongsTo(TrainingClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
