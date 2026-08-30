<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class UnarchiveUserAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $before = $locked->load('profile', 'currentRole')->toArray();
            $locked->forceFill([
                'status' => UserStatus::Active,
                'archived_at' => null,
                'session_version' => $locked->session_version + 1,
            ])->save();
            $this->audit->record('user.unarchived', $locked, $before, $locked->fresh('profile', 'currentRole')->toArray());

            return $locked->fresh('profile', 'currentRole');
        });
    }
}
