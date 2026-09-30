<?php

namespace Database\Factories;

use App\Models\TrainingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_number' => 'TP-'.fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (TrainingProvider $provider): void {
            if ($provider->locations()->doesntExist() && filled($provider->address)) {
                $provider->locations()->create([
                    'location' => $provider->address,
                    'latitude' => $provider->latitude,
                    'longitude' => $provider->longitude,
                ]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => 'archived', 'archived_at' => now()->subDays(10)]);
    }
}
