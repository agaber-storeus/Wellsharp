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
            'status' => 'active',
        ];
    }
}
