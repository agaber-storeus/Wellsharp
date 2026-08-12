<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_number' => 'TP-'.fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'status' => 'active',
        ];
    }
}
