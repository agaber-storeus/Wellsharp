<?php

namespace Database\Factories;

use App\Models\TrainingProvider;
use App\Models\TrainingProviderLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrainingProviderLocation> */
class TrainingProviderLocationFactory extends Factory
{
    protected $model = TrainingProviderLocation::class;

    public function definition(): array
    {
        return [
            'training_provider_id' => TrainingProvider::factory(),
            'location' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
