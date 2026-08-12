<?php

namespace App\Actions\Groups;

use App\Enums\GroupStatus;
use App\Models\Group;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class SaveGroupAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(?Group $group, array $data): Group
    {
        return DB::transaction(function () use ($group, $data): Group {
            $creating = $group === null;
            if ($creating) {
                $group = Group::create([
                    'name' => trim($data['name']),
                    'code' => filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : null,
                    'description' => $data['description'] ?? null,
                    'status' => GroupStatus::Active,
                    'created_by_user_id' => auth()->id(),
                    'updated_by_user_id' => auth()->id(),
                ]);
                $before = null;
            } else {
                $group = Group::query()->lockForUpdate()->findOrFail($group->getKey());
                $before = $group->toArray();
                $group->update([
                    'name' => trim($data['name']),
                    'code' => filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : null,
                    'description' => $data['description'] ?? null,
                    'updated_by_user_id' => auth()->id(),
                ]);
            }
            $this->audit->record($creating ? 'group.created' : 'group.updated', $group, $before, $group->fresh()->toArray());

            return $group->fresh();
        });
    }
}
