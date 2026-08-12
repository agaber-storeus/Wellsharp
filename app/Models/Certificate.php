<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'certificate_number', 'exam_attempt_id', 'student_user_id', 'exam_id', 'exam_schedule_id',
        'training_class_id', 'training_provider_id', 'instructor_user_id', 'student_name',
        'student_email', 'student_wellsharp_id', 'exam_name', 'exam_code', 'subject_name',
        'class_number', 'group_name', 'provider_name', 'instructor_name', 'score',
        'passing_score', 'issued_at', 'expires_at', 'status', 'revoked_at', 'revocation_reason', 'pdf_path',
        'issued_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CertificateStatus::class,
            'score' => 'decimal:2',
            'passing_score' => 'integer',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id');
    }

    public function trainingClass(): BelongsTo
    {
        return $this->belongsTo(TrainingClass::class, 'training_class_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TrainingProvider::class, 'training_provider_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_user_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CertificateDocument::class)->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
