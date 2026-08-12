<?php

namespace App\Models;

use App\Enums\GroupStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory, HasPublicUlid;

    protected $table = 'student_groups';

    protected $fillable = ['name', 'code', 'description', 'status', 'created_by_user_id', 'updated_by_user_id'];

    protected function casts(): array
    {
        return ['status' => GroupStatus::class];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_memberships', 'group_id', 'student_user_id')
            ->wherePivot('status', 'active')
            ->withPivot(['public_id', 'status', 'joined_at', 'removed_at']);
    }

    public function examAssignments(): HasMany
    {
        return $this->hasMany(ExamGroupAssignment::class);
    }

    public function examSchedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
