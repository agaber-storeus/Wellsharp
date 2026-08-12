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
            'status' => 'planned',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ];
    }
}
