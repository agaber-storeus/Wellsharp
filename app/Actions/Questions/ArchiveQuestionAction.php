<?php

namespace App\Actions\Questions;

use App\Models\Question;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class ArchiveQuestionAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Question $question): Question
    {
        return DB::transaction(function () use ($question): Question {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());
            $question->update(['is_active' => false, 'updated_by_user_id' => auth()->id()]);
            $this->audit->record('question.archived', $question, ['is_active' => true], ['is_active' => false]);

            return $question;
        });
    }
}
