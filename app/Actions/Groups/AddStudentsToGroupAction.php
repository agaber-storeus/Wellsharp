<?php

namespace App\Actions\Groups;

use App\Enums\GroupMembershipStatus;
use App\Actions\Classes\SyncGroupEnrollmentsAction;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddStudentsToGroupAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SyncGroupEnrollmentsAction $syncGroupEnrollments,
    ) {}

    public function execute(Group $group, array $studentIds): int
    {
        return DB::transaction(function () use ($group, $studentIds): int {
            $group = Group::query()->lockForUpdate()->findOrFail($group->getKey());
            $studentRoleId = Role::where('key', Role::STUDENT)->value('id');
            $students = User::query()->whereIn('id', $studentIds)->where('current_role_id', $studentRoleId)->get();
            if ($students->count() !== count(array_unique($studentIds))) {
                throw ValidationException::withMessages(['student_ids' => 'Only existing Student users can be added to a group.']);
            }
            foreach ($students as $student) {
                if (GroupMembership::query()->where('group_id', $group->getKey())->where('student_user_id', $student->getKey())->where('status', GroupMembershipStatus::Active)->exists()) {
                    throw ValidationException::withMessages(['student_ids' => "{$student->display_name} is already an active member of this group."]);
                }
                GroupMembership::create([
                    'group_id' => $group->getKey(),
                    'student_user_id' => $student->getKey(),
                    'status' => GroupMembershipStatus::Active,
                    'joined_at' => now(),
                    'created_by_user_id' => auth()->id(),
                ]);
            }
            $this->syncGroupEnrollments->executeForGroup($group);
            $this->audit->record('group.student_added', $group, null, ['group_id' => $group->getKey(), 'student_count' => $students->count()]);

            return $students->count();
        });
    }
}
