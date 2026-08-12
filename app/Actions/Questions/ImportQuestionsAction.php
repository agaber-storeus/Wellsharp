<?php

namespace App\Actions\Questions;

use App\Models\Course;
use App\Models\Question;
use App\Services\AuditRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ImportQuestionsAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly CreateQuestionAction $questions,
    ) {}

    public function execute(Course $course, array $rows, string $filename): int
    {
        try {
            return DB::transaction(function () use ($course, $rows, $filename): int {
                $seen = [];
                $existing = $course->questions()->pluck('question_text')->map(fn ($text): string => Question::normalizeText($text))->all();
                foreach ($rows as $row) {
                    $normalized = Question::normalizeText($row['question_text']);
                    if (in_array($normalized, $existing, true) || in_array($normalized, $seen, true)) {
                        throw new \InvalidArgumentException('The import contains a duplicate question for this course.');
                    }
                    $seen[] = $normalized;
                    $question = Question::create(CreateQuestionAction::attributes($course, $row));
                    $this->questions->syncOptions($question, $row);
                }

                $count = count($rows);
                $this->audit->record('questions.imported', null, null, [
                    'course_id' => $course->getKey(), 'count' => $count,
                    'filename' => basename($filename),
                ]);

                return $count;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000' && str_contains($exception->getMessage(), 'question_text_hash')) {
                throw new \InvalidArgumentException('The import contains a duplicate question for this course.', 0, $exception);
            }

            throw $exception;
        }
    }
}
