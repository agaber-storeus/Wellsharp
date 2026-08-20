<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamQuestion>
 *
 * `display_order` defaults to 0 (single-row-safe, per the unique(exam_id,
 * display_order) and unique(exam_id, question_id) constraints). Attaching
 * several questions to the same exam requires ->sequence(...).
 */
class ExamQuestionFactory extends Factory
{
    protected $model = ExamQuestion::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'question_id' => Question::factory(),
            'display_order' => 0,
            'points' => 1,
        ];
    }
}
