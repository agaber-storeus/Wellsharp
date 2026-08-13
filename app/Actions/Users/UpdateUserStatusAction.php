<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class UpdateUserStatusAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(User $user, UserStatus $status): User
    {
        return DB::transaction(function () use ($user, $status): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $before = $locked->load('profile', 'currentRole')->toArray();
            $locked->forceFill([
                'status' => $status,
                'archived_at' => $status === UserStatus::Active ? null : now(),
                'session_version' => $locked->session_version + 1,
            ])->save();
            $this->audit->record('user.status_updated', $locked, $before, $locked->fresh('profile', 'currentRole')->toArray());

            return $locked->fresh('profile', 'currentRole');
        });
    }
}
