<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleAssignmentFactory extends Factory
{
    protected $model = RoleAssignment::class;

    public function definition(): array
    {
        return [
            // Plain User::factory() (no role state) never triggers an
            // automatic RoleAssignment of its own, so this stays collision-free.
            'user_id' => User::factory(),
            'role_id' => fn (): int => Role::query()->value('id') ?? Role::factory()->create()->id,
            'started_at' => now(),
            'ended_at' => null,
            'assigned_by_user_id' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (): array => ['ended_at' => now()]);
    }
}
