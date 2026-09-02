<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'CRS-'.fake()->unique()->numerify('#####'),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'course_level_id' => null,
            'status' => 'active',
        ];
    }

    public function retired(): static
    {
        return $this->state(fn (): array => ['status' => 'retired']);
    }
}
