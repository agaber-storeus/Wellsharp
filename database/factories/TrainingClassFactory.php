<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\TrainingClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingClassFactory extends Factory
{
    protected $model = TrainingClass::class;

    public function definition(): array
    {
        return [
            'class_number' => 'CLASS-'.fake()->unique()->numerify('#####'),
            'course_id' => Course::factory(),
            'training_provider_id' => null,
            'status' => 'planned',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'actual_started_at' => null,
            'actual_ended_at' => null,
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'actual_started_at' => now()->subDay()->addMinutes(15),
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (): array {
            $startsAt = now()->subDays(30);

            return [
                'status' => 'completed',
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDays(2),
                'actual_started_at' => $startsAt->copy()->addMinutes(20),
                'actual_ended_at' => $startsAt->copy()->addDays(2)->subMinutes(10),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => 'cancelled', 'starts_at' => now()->subDays(14), 'ends_at' => now()->subDays(12)]);
    }
}
