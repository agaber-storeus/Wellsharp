<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\ProctorIdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeUserRoleAction
{
    public function __construct(private readonly AuditRecorder $audit, private readonly ProctorIdGenerator $controlIds) {}

    public function execute(User $user, Role $role): User
    {
        return DB::transaction(function () use ($user, $role): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $before = $locked->load('currentRole', 'profile')->toArray();

            if ($locked->current_role_id === $role->getKey()) {
                throw ValidationException::withMessages(['role_id' => 'The user already has this role.']);
            }

            $wasStudent = $locked->currentRole?->key === Role::STUDENT;
            $willBeStudent = $role->key === Role::STUDENT;
            $locked->roleAssignments()->whereNull('ended_at')->update(['ended_at' => now()]);
            RoleAssignment::create([
                'user_id' => $locked->getKey(),
                'role_id' => $role->getKey(),
                'assigned_by_user_id' => auth()->id(),
                'started_at' => now(),
            ]);
            $locked->forceFill([
                'current_role_id' => $role->getKey(),
                'session_version' => $locked->session_version + 1,
            ])->save();
            if (in_array($role->key, [Role::PROCTOR, Role::INSTRUCTOR], true)) {
                $locked->examControlCredential()->firstOrCreate([], ['control_id' => $this->controlIds->generate()]);
            } else {
                $locked->examControlCredential()->delete();
            }
            $locked->profile()->update([
                'state' => $willBeStudent || $wasStudent ? null : $locked->profile?->state,
                'company_contact' => $willBeStudent && $wasStudent ? $locked->profile?->company_contact : null,
                'age' => $willBeStudent && $wasStudent ? $locked->profile?->age : null,
                'gender' => $willBeStudent && $wasStudent ? $locked->profile?->gender : null,
            ]);
            $this->audit->record('user.role_changed', $locked, $before, $locked->fresh('currentRole', 'profile')->toArray());

            return $locked->fresh('currentRole', 'profile');
        });
    }
}
