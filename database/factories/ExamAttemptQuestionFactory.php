<?php

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttemptQuestion>
 *
 * `display_order` defaults to 0 (single-row-safe, per the unique(exam_attempt_id,
 * display_order) and unique(exam_attempt_id, question_id) constraints).
 */
class ExamAttemptQuestionFactory extends Factory
{
    protected $model = ExamAttemptQuestion::class;

    public function definition(): array
    {
        return [
            'exam_attempt_id' => ExamAttempt::factory(),
            'question_id' => Question::factory(),
            'display_order' => 0,
            'points' => 1,
            'answer' => null,
            'answered_at' => null,
            'option_order' => null,
        ];
    }

    public function answered(string $answer): static
    {
        return $this->state(fn (): array => ['answer' => $answer, 'answered_at' => now()]);
    }
}
