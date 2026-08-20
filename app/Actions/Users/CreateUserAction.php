<?php

namespace App\Actions\Users;

use App\Actions\Groups\SyncStudentGroupsAction;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuditRecorder;
use App\Services\ProctorIdGenerator;
use App\Services\TemporaryPasswordGenerator;
use App\Services\UserIdentityGenerator;
use Illuminate\Support\Facades\DB;

class CreateUserAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SyncStudentGroupsAction $syncStudentGroups,
        private readonly ProctorIdGenerator $controlIds,
        private readonly UserIdentityGenerator $identity,
        private readonly TemporaryPasswordGenerator $passwords,
    ) {}

    public function execute(array $data): CreateUserResult
    {
        return DB::transaction(function () use ($data): CreateUserResult {
            $role = Role::whereKey($data['role_id'])->firstOrFail();
            $firstName = trim($data['first_name']);
            $lastName = trim($data['last_name']);

            $wellsharpId = filled($data['wellsharp_id'] ?? null)
                ? strtoupper(trim($data['wellsharp_id']))
                : $this->identity->generateWellsharpId($firstName, $lastName);
            $username = $this->identity->generateUsername($firstName, $lastName);
            $generatedPassword = filled($data['password'] ?? null) ? null : $this->generatePassword($role->key, $wellsharpId, $username);
            $password = $generatedPassword ?? $data['password'];

            $user = new User([
                'wellsharp_id' => $wellsharpId,
                'email' => $data['email'] ?? null,
            ]);
            $user->setPasswordAndCiphertext($password, $role->key);
            $user->forceFill([
                'username' => $username,
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
                'age' => $role->key === Role::STUDENT ? UserProfile::calculateAge($data['birthday'] ?? null) : null,
                'gender' => $role->key === Role::STUDENT ? ($data['gender'] ?? null) : null,
            ]);
            RoleAssignment::create([
                'user_id' => $user->getKey(),
                'role_id' => $role->getKey(),
                'assigned_by_user_id' => auth()->id(),
                'started_at' => now(),
            ]);
            if ($this->controlIds->eligibleForRole($role->key)) {
                $user->examControlCredential()->create(['control_id' => $this->controlIds->generate()]);
            }
            if ($role->key === Role::STUDENT) {
                $this->syncStudentGroups->execute($user, $data['group_ids'] ?? []);
            }
            $this->audit->record($role->key === Role::STUDENT ? 'student.created' : 'user.created', $user, null, $user->load('profile', 'currentRole')->toArray());

            return new CreateUserResult($user->load('profile', 'currentRole'), $generatedPassword);
        });
    }

    /**
     * Regenerates on the (astronomically unlikely) chance a random password
     * collides case-insensitively with an identifier it must never equal.
     */
    private function generatePassword(string $roleKey, string $wellsharpId, string $username): string
    {
        $forbidden = array_map(strtolower(...), array_filter([$wellsharpId, $username]));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $password = $this->passwords->generateForRole($roleKey);
            if (! in_array(strtolower($password), $forbidden, true)) {
                return $password;
            }
        }

        return $this->passwords->generateForRole($roleKey);
    }
}
