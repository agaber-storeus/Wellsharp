<?php

namespace App\Actions\Groups;

use App\Enums\GroupMembershipStatus;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveStudentFromGroupAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Group $group, User $student): GroupMembership
    {
        return DB::transaction(function () use ($group, $student): GroupMembership {
            $membership = GroupMembership::query()->where('group_id', $group->getKey())->where('student_user_id', $student->getKey())->where('status', GroupMembershipStatus::Active)->lockForUpdate()->first();
            if (! $membership) {
                throw ValidationException::withMessages(['student' => 'The student is not an active member of this group.']);
            }
            $membership->update(['status' => GroupMembershipStatus::Removed, 'removed_at' => now()]);
            $this->audit->record('group.student_removed', $group, ['student_user_id' => $student->getKey()], ['membership_id' => $membership->getKey()]);

            return $membership;
        });
    }
}
