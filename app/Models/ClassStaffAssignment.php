<?php

namespace App\Models;

use App\Enums\StaffAssignmentRole;
use App\Enums\StaffAssignmentStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassStaffAssignment extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['class_id', 'user_id', 'assignment_role', 'status', 'assigned_by_user_id', 'assigned_at', 'ended_at'];

    protected function casts(): array
    {
        return ['assignment_role' => StaffAssignmentRole::class, 'status' => StaffAssignmentStatus::class, 'assigned_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function trainingClass(): BelongsTo
    {
        return $this->belongsTo(TrainingClass::class, 'class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
