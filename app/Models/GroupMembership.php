<?php

namespace App\Models;

use App\Enums\GroupMembershipStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMembership extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['group_id', 'student_user_id', 'status', 'joined_at', 'removed_at', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['status' => GroupMembershipStatus::class, 'joined_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
