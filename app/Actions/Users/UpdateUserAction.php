<?php

namespace App\Actions\Users;

use App\Actions\Groups\SyncStudentGroupsAction;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuditRecorder;
use App\Services\UserIdentityGenerator;
use Illuminate\Support\Facades\DB;

class UpdateUserAction
{
    public function __construct(private readonly AuditRecorder $audit, private readonly SyncStudentGroupsAction $syncStudentGroups, private readonly UserIdentityGenerator $identity) {}

    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $before = $locked->load('profile', 'currentRole')->toArray();
            $passwordChanged = filled($data['password'] ?? null);
            $wellsharpId = array_key_exists('wellsharp_id', $data) ? $this->identity->normalizeWellsharpId($data['wellsharp_id']) : $locked->wellsharp_id;
            $username = array_key_exists('username', $data) ? $this->identity->normalizeUsername($data['username']) : $locked->username;
            $wellsharpIdChanged = $locked->wellsharp_id !== $wellsharpId;
            $usernameChanged = $locked->username !== $username;

            $locked->fill([
                'wellsharp_id' => $wellsharpId,
                'email' => $data['email'] ?? null,
            ]);
            $locked->forceFill(['username' => $username]);
            if ($passwordChanged) {
                $locked->setPasswordAndCiphertext($data['password'], $locked->currentRole?->key ?? '');
            }
            if ($passwordChanged || $wellsharpIdChanged || $usernameChanged) {
                $locked->session_version++;
            }
            $locked->save();
            if ($locked->currentRole?->key === Role::PROCTOR && filled($data['proctor_id'] ?? null)) {
                $locked->examControlCredential()->updateOrCreate([], ['control_id' => strtoupper(trim($data['proctor_id']))]);
            }
            $locked->profile()->updateOrCreate([], [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'birthday' => $data['birthday'] ?? null,
                'address' => $data['address'] ?? null,
                'country' => $data['country'] ?? null,
                'state' => $locked->currentRole?->key === Role::STUDENT ? null : ($data['state'] ?? null),
                'city' => $data['city'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'company' => $data['company'] ?? null,
                'position' => $data['position'] ?? null,
                'company_contact' => $locked->currentRole?->key === Role::STUDENT ? ($data['company_contact'] ?? null) : null,
                'employee_id' => $data['employee_id'] ?? null,
                'age' => $locked->currentRole?->key === Role::STUDENT ? UserProfile::calculateAge($data['birthday'] ?? null) : null,
                'gender' => $locked->currentRole?->key === Role::STUDENT ? ($data['gender'] ?? null) : null,
            ]);
            if ($locked->currentRole?->key === Role::STUDENT) {
                $this->syncStudentGroups->execute($locked, $data['group_ids'] ?? []);
            }
            $this->audit->record($locked->currentRole?->key === Role::STUDENT ? 'student.updated' : 'user.updated', $locked, $before, $locked->fresh('profile', 'currentRole')->toArray());

            return $locked->fresh('profile', 'currentRole');
        });
    }
}
