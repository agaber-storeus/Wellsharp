<?php

namespace App\Actions\Users;

use App\Actions\Groups\SyncStudentGroupsAction;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\ProctorIdGenerator;
use Illuminate\Support\Facades\DB;

class CreateUserAction
{
    public function __construct(private readonly AuditRecorder $audit, private readonly SyncStudentGroupsAction $syncStudentGroups, private readonly ProctorIdGenerator $controlIds) {}

    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $role = Role::whereKey($data['role_id'])->firstOrFail();
            $user = User::create([
                'wellsharp_id' => strtoupper(trim($data['wellsharp_id'])),
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
            ]);
            $user->forceFill([
                'status' => UserStatus::Active,
                'current_role_id' => $role->getKey(),
            ])->save();
            $user->profile()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'birthday' => $data['birthday'] ?? null,
                'address' => $data['address'] ?? null,
                'country' => $data['country'] ?? null,
                'state' => $role->key === Role::STUDENT ? null : ($data['state'] ?? null),
                'city' => $data['city'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'company' => $data['company'] ?? null,
                'position' => $data['position'] ?? null,
                'company_contact' => $role->key === Role::STUDENT ? ($data['company_contact'] ?? null) : null,
                'employee_id' => $data['employee_id'] ?? null,
                'age' => $role->key === Role::STUDENT ? ($data['age'] ?? null) : null,
                'gender' => $role->key === Role::STUDENT ? ($data['gender'] ?? null) : null,
            ]);
            RoleAssignment::create([
                'user_id' => $user->getKey(),
                'role_id' => $role->getKey(),
                'assigned_by_user_id' => auth()->id(),
                'started_at' => now(),
            ]);
            if (in_array($role->key, [Role::PROCTOR, Role::INSTRUCTOR], true)) {
                $user->examControlCredential()->create(['control_id' => $this->controlIds->generate()]);
            }
            if ($role->key === Role::STUDENT) {
                $this->syncStudentGroups->execute($user, $data['group_ids'] ?? []);
            }
            $this->audit->record($role->key === Role::STUDENT ? 'student.created' : 'user.created', $user, null, $user->load('profile', 'currentRole')->toArray());

            return $user->load('profile', 'currentRole');
        });
    }
}
