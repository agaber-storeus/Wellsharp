<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return ['class_id' => TrainingClass::factory(), 'student_user_id' => User::factory()->student(), 'status' => EnrollmentStatus::Enrolled, 'enrolled_at' => now()];
    }
}
