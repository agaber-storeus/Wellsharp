<?php

namespace App\Actions\Questions;

use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use App\Services\AuditRecorder;
use App\Services\QuestionImageService;
use Illuminate\Support\Facades\DB;

class CreateQuestionAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly QuestionImageService $images,
    ) {}

    public function execute(Course $course, array $data): Question
    {
        return DB::transaction(function () use ($course, $data): Question {
            $question = Question::create($this->attributes($course, $data));
            $this->syncImages($question, $data);
            $this->syncOptions($question, $data);
            $question->load('options');
            $this->audit->record('question.created', $question, null, [
                'course_id' => $course->getKey(), 'question_id' => $question->getKey(),
                'type' => $question->type->value, 'difficulty' => $question->difficulty->value,
            ]);

            return $question;
        });
    }

    public static function attributes(Course $course, array $data): array
    {
        $type = QuestionType::from($data['type']);

        return [
            'course_id' => $course->getKey(),
            'question_text' => trim($data['question_text']),
            'type' => $type,
            'difficulty' => $data['difficulty'],
            'default_marks' => $data['default_marks'] ?? 1,
            'correct_answer_text' => $type === QuestionType::Input ? trim((string) ($data['correct_answer'] ?? '')) : null,
            'correct_answer_boolean' => $type === QuestionType::TrueFalse ? self::booleanAnswer($data['correct_answer']) : null,
            'solution_text' => isset($data['solution_text']) ? trim((string) $data['solution_text']) ?: null : null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'created_by_user_id' => auth()->id(),
            'updated_by_user_id' => auth()->id(),
        ];
    }

    public function syncOptions(Question $question, array $data): void
    {
        $existingOptions = $question->exists
            ? $question->options()->orderBy('display_order')->get()
            : collect();

        if (QuestionType::from($data['type']) !== QuestionType::Mcq) {
            foreach ($existingOptions as $existingOption) {
                $this->images->delete($existingOption->image_path);
            }
            $question->options()->delete();

            return;
        }

        $options = array_values(array_filter(array_map('trim', $data['options'] ?? []), fn (string $option): bool => $option !== ''));
        $uploadedImages = $data['option_images'] ?? [];
        $removedImages = $data['remove_option_images'] ?? [];
        $newPaths = [];
        $correctIndex = (int) $data['correct_option'];
        $question->options()->delete();
        foreach ($options as $index => $option) {
            $existingPath = $existingOptions->get($index)?->image_path;
            $newPaths[] = $this->images->replace(
                $existingPath,
                $uploadedImages[$index] ?? null,
                array_key_exists($index, $removedImages),
                'questions/'.$question->public_id.'/options',
            );
            $question->options()->create([
                'option_text' => $option,
                'image_path' => $newPaths[$index],
                'is_correct' => $index === $correctIndex,
                'display_order' => $index,
            ]);
        }

        foreach ($existingOptions as $existingOption) {
            if ($existingOption->image_path && ! in_array($existingOption->image_path, $newPaths, true)) {
                $this->images->delete($existingOption->image_path);
            }
        }
    }

    public function syncImages(Question $question, array $data): void
    {
        $question->update([
            'question_image_path' => $this->images->replace(
                $question->question_image_path,
                $data['question_image'] ?? null,
                (bool) ($data['remove_question_image'] ?? false),
                'questions/'.$question->public_id.'/question',
            ),
            'correct_answer_image_path' => $this->images->replace(
                $question->correct_answer_image_path,
                $data['correct_answer_image'] ?? null,
                (bool) ($data['remove_correct_answer_image'] ?? false),
                'questions/'.$question->public_id.'/answer',
            ),
        ]);
    }

    private static function booleanAnswer(string $answer): bool
    {
        return in_array(strtolower(trim($answer)), ['true', '1', 'yes'], true);
    }
}
