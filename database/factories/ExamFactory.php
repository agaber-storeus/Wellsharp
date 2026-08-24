<?php

namespace Database\Factories;

use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 *
 * `code` is intentionally left unset: Exam::booted()'s `creating` hook
 * generates it via ExamCodeGenerator when blank, matching real exam
 * creation instead of duplicating that generator here.
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => ucfirst(fake()->unique()->words(4, true)).' Exam',
            'description' => fake()->sentence(),
            'passing_score' => 75,
            'retake_score' => 60,
            'certificate_validity_years' => 2,
            'question_order_mode' => ExamQuestionOrderMode::Static,
            'status' => ExamStatus::Draft,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => ExamStatus::Published]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => ExamStatus::Archived]);
    }

    public function shuffle(): static
    {
        return $this->state(fn (): array => ['question_order_mode' => ExamQuestionOrderMode::Shuffle]);
    }
}
