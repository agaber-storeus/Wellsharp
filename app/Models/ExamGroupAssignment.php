<?php

namespace App\Models;

use App\Enums\ExamGroupAssignmentStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamGroupAssignment extends Model
{
    /** Legacy compatibility record. New group eligibility belongs to ExamSchedule. */
    use HasFactory, HasPublicUlid;

    protected $fillable = ['exam_id', 'group_id', 'status', 'assigned_by_user_id', 'assigned_at', 'removed_at'];

    protected function casts(): array
    {
        return ['status' => ExamGroupAssignmentStatus::class, 'assigned_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
