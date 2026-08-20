<?php

namespace Database\Factories;

use App\Enums\GroupStatus;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(3, true)).' Group',
            'code' => 'GRP-'.fake()->unique()->numerify('#####'),
            'description' => fake()->sentence(),
            'status' => GroupStatus::Active,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => GroupStatus::Archived]);
    }
}
