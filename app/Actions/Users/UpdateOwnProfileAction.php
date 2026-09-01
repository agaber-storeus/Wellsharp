<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\QuestionImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateOwnProfileAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly QuestionImageService $images,
    ) {}

    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $before = $locked->load('profile', 'currentRole')->toArray();
            $passwordChanged = filled($data['password'] ?? null);

            $locked->email = $data['email'] ?? null;
            if ($passwordChanged) {
                $locked->setPasswordAndCiphertext($data['password'], $locked->currentRole?->key ?? '');
                $locked->session_version++;
            }
            $locked->save();
            $profile = $locked->profile()->first();
            $profileData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'birthday' => $data['birthday'] ?? null,
                'address' => $data['address'] ?? null,
                'country' => $data['country'] ?? null,
                'state' => $data['state'] ?? null,
                'city' => $data['city'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'company' => $data['company'] ?? null,
                'position' => $data['position'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
            ];
            if (($data['profile_photo'] ?? null) instanceof UploadedFile) {
                $profileData['profile_photo_path'] = $this->images->replace(
                    $profile?->profile_photo_path,
                    $data['profile_photo'],
                    false,
                    'profile-photos',
                );
            }
            $locked->profile()->updateOrCreate([], $profileData);
            $this->audit->record('user.profile_updated', $locked, $before, $locked->fresh('profile', 'currentRole')->toArray());

            return $locked->fresh('profile', 'currentRole');
        });
    }
}
