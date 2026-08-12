<?php

namespace App\Actions\Questions;

use App\Models\Question;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class UpdateQuestionAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly CreateQuestionAction $questions,
    ) {}

    public function execute(Question $question, array $data): Question
    {
        return DB::transaction(function () use ($question, $data): Question {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());
            $before = ['course_id' => $question->course_id, 'type' => $question->type->value, 'difficulty' => $question->difficulty->value];
            $question->update(array_merge(CreateQuestionAction::attributes($question->course, $data), [
                'created_by_user_id' => $question->created_by_user_id,
                'updated_by_user_id' => auth()->id(),
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $question->is_active,
            ]));
            $this->questions->syncImages($question, $data);
            $this->questions->syncOptions($question, $data);
            $question->load('options');
            $this->audit->record('question.updated', $question, $before, [
                'course_id' => $question->course_id, 'question_id' => $question->getKey(),
                'type' => $question->type->value, 'difficulty' => $question->difficulty->value,
            ]);

            return $question;
        });
    }
}
