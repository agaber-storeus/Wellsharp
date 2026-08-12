<?php

namespace App\Actions\Exams;

use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\Exam;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveExamAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(?Exam $exam, Course $course, array $data): Exam
    {
        return DB::transaction(function () use ($exam, $course, $data): Exam {
            $creating = $exam === null;
            if ($creating) {
                $exam = Exam::create([
                    'course_id' => $course->getKey(),
                    'name' => trim($data['name']),
                    'code' => filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : null,
                    'description' => $data['description'] ?? null,
                    'passing_score' => $data['passing_score'] ?? null,
                    'retake_score' => $data['retake_score'] ?? null,
                    'question_order_mode' => $data['question_order_mode'],
                    'status' => $data['status'] ?? ExamStatus::Draft,
                    'created_by_user_id' => auth()->id(),
                    'updated_by_user_id' => auth()->id(),
                ]);
                $before = null;
            } else {
                $exam = Exam::query()->lockForUpdate()->findOrFail($exam->getKey());
                $before = ['name' => $exam->name, 'status' => $exam->status->value, 'question_order_mode' => $exam->question_order_mode->value];
                $exam->update([
                    'name' => trim($data['name']),
                    'code' => filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : null,
                    'description' => $data['description'] ?? null,
                    'passing_score' => $data['passing_score'] ?? null,
                    'retake_score' => $data['retake_score'] ?? null,
                    'question_order_mode' => $data['question_order_mode'],
                    'status' => $data['status'] ?? $exam->status,
                    'updated_by_user_id' => auth()->id(),
                ]);
            }
            $this->syncQuestions($exam, $data);
            $exam->load('examQuestions.question');
            $this->audit->record($creating ? 'exam.created' : 'exam.updated', $exam, $before, [
                'course_id' => $course->getKey(), 'exam_id' => $exam->getKey(),
                'question_count' => $exam->examQuestions->count(),
                'question_order_mode' => $exam->question_order_mode->value,
            ]);
            $this->audit->record('exam.questions_updated', $exam, null, ['exam_id' => $exam->getKey(), 'question_count' => $exam->examQuestions->count()]);

            return $exam->fresh(['subject', 'examQuestions.question', 'groups', 'schedules']);
        });
    }

    private function syncQuestions(Exam $exam, array $data): void
    {
        $questionIds = array_values(array_unique(array_map('intval', $data['question_ids'] ?? [])));
        $orders = $data['display_orders'] ?? [];
        $questions = $exam->subject->questions()->whereIn('id', $questionIds)->pluck('id')->all();
        if (count($questions) !== count($questionIds)) {
            throw ValidationException::withMessages(['question_ids' => 'Every exam question must belong to the selected Subject.']);
        }
        sort($questions);
        $selected = array_values(array_intersect($questionIds, $questions));
        $ordered = [];
        foreach ($selected as $index => $questionId) {
            $ordered[$questionId] = isset($orders[$questionId]) ? (int) $orders[$questionId] : $index + 1;
        }
        uasort($ordered, fn (int $left, int $right): int => $left <=> $right);
        $exam->examQuestions()->delete();
        foreach (array_keys($ordered) as $index => $questionId) {
            $exam->examQuestions()->create(['question_id' => $questionId, 'display_order' => $index + 1]);
        }
    }
}
