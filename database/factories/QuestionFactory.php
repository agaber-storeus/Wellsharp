<?php

namespace Database\Factories;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * `code` is intentionally left unset: Question::booted()'s `creating`
     * hook generates it via QuestionCodeGenerator (matching questions.code's
     * NOT NULL/unique constraint) exactly like real question creation does.
     * Duplicating that generator here would risk a second, competing format.
     */
    public function definition(): array
    {
        $type = fake()->randomElement(QuestionType::cases());

        return [
            'course_id' => Course::factory(),
            'question_text' => ucfirst(fake()->unique()->sentence(8)),
            'type' => $type,
            'difficulty' => fake()->randomElement(QuestionDifficulty::cases()),
            'default_marks' => $type === QuestionType::Mcq ? 2 : 1,
            'correct_answer_text' => $type === QuestionType::Input ? ucfirst(fake()->words(3, true)) : null,
            'correct_answer_boolean' => $type === QuestionType::TrueFalse ? fake()->boolean() : null,
            'solution_text' => fake()->sentence(),
            'is_active' => true,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ];
    }

    public function mcq(): static
    {
        return $this->state(fn (): array => ['type' => QuestionType::Mcq, 'default_marks' => 2, 'correct_answer_text' => null, 'correct_answer_boolean' => null]);
    }

    public function trueFalse(bool $correct = true): static
    {
        return $this->state(fn (): array => ['type' => QuestionType::TrueFalse, 'default_marks' => 1, 'correct_answer_text' => null, 'correct_answer_boolean' => $correct]);
    }

    public function input(string $answer = 'Approved operating procedure'): static
    {
        return $this->state(fn (): array => ['type' => QuestionType::Input, 'default_marks' => 1, 'correct_answer_text' => $answer, 'correct_answer_boolean' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Attaches 4 MCQ options (one correct) once the Question is persisted.
     * Opt-in on purpose: a plain Question::factory()->create() should never
     * silently insert extra rows a caller didn't ask for.
     */
    public function withOptions(int $correctIndex = 0): static
    {
        return $this->mcq()->afterCreating(function (Question $question) use ($correctIndex): void {
            foreach (range(0, 3) as $index) {
                $question->options()->create([
                    'option_text' => ucfirst(fake()->words(3, true)),
                    'is_correct' => $index === $correctIndex,
                    'display_order' => $index,
                ]);
            }
        });
    }
}
