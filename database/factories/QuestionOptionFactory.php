<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 *
 * `display_order` defaults to 0 (single-option-safe, per question_options'
 * unique(question_id, display_order) constraint). Creating several options
 * for the same question requires ->sequence(fn ($s) => ['display_order' => $s->index]).
 */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory()->mcq(),
            'option_text' => ucfirst(fake()->words(3, true)),
            'is_correct' => false,
            'display_order' => 0,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (): array => ['is_correct' => true]);
    }
}
