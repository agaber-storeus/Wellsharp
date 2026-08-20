<?php

namespace Database\Factories;

use App\Enums\GroupMembershipStatus;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupMembershipFactory extends Factory
{
    protected $model = GroupMembership::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'student_user_id' => User::factory()->student(),
            'status' => GroupMembershipStatus::Active,
            'joined_at' => now(),
            'removed_at' => null,
            'created_by_user_id' => null,
        ];
    }

    public function removed(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupMembershipStatus::Removed,
            'joined_at' => now()->subDays(60),
            'removed_at' => now()->subDays(10),
        ]);
    }
}
