<?php

namespace App\Actions\Groups;

use App\Actions\Classes\SyncGroupEnrollmentsAction;
use App\Enums\GroupMembershipStatus;
use App\Enums\GroupStatus;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Validation\ValidationException;

class SyncStudentGroupsAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SyncGroupEnrollmentsAction $syncGroupEnrollments,
    ) {}

    /** @param array<int, int|string> $groupIds */
    public function execute(User $student, array $groupIds): void
    {
        $groupIds = collect($groupIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $groups = Group::query()->whereIn('id', $groupIds)->where('status', GroupStatus::Active)->get()->keyBy('id');

        if ($groups->count() !== $groupIds->count()) {
            throw ValidationException::withMessages(['group_ids' => 'Select only active Groups.']);
        }

        $activeMemberships = GroupMembership::query()
            ->where('student_user_id', $student->getKey())
            ->where('status', GroupMembershipStatus::Active)
            ->lockForUpdate()
            ->get()
            ->keyBy('group_id');

        foreach ($activeMemberships as $membership) {
            if (! $groupIds->contains((int) $membership->group_id)) {
                $membership->update([
                    'status' => GroupMembershipStatus::Removed,
                    'removed_at' => now(),
                ]);
                $this->audit->record('group.student_removed', $membership->group, ['student_user_id' => $student->getKey()], ['membership_id' => $membership->getKey()]);
            }
        }

        foreach ($groups as $group) {
            if ($activeMemberships->has($group->getKey())) {
                continue;
            }
            $membership = GroupMembership::create([
                'group_id' => $group->getKey(),
                'student_user_id' => $student->getKey(),
                'status' => GroupMembershipStatus::Active,
                'joined_at' => now(),
                'created_by_user_id' => auth()->id(),
            ]);
            $this->audit->record('group.student_added', $group, null, ['student_user_id' => $student->getKey(), 'membership_id' => $membership->getKey()]);
            $this->syncGroupEnrollments->executeForGroup($group);
        }
    }
}
