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
        return [
            'class_id' => TrainingClass::factory(),
            'student_user_id' => User::factory()->student(),
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'withdrawn_at' => null,
            'skills_score' => null,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn (): array => ['status' => EnrollmentStatus::Withdrawn, 'withdrawn_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => EnrollmentStatus::Completed]);
    }

    /**
     * A Proctor records the Skills Score by hand after a hands-on
     * assessment (see NavigationController's min:0/max:100 validation) - it
     * is never derived, so the factory just fills a valid value on request.
     */
    public function withSkillsScore(?int $score = null): static
    {
        return $this->state(fn (): array => ['skills_score' => $score ?? fake()->numberBetween(0, 100)]);
    }
}
