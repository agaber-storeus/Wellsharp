<?php

namespace App\Actions\Exams;

use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamQuestionSelectionMode;
use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\Exam;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveExamAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SaveExamScheduleAction $scheduler,
    ) {}

    public function execute(?Exam $exam, Course $course, array $data): Exam
    {
        return DB::transaction(function () use ($exam, $course, $data): Exam {
            $creating = $exam === null;
            $selectionMode = $data['question_selection_mode'] ?? ExamQuestionSelectionMode::Manual->value;
            $questionOrderMode = $selectionMode === ExamQuestionSelectionMode::Random->value
                ? ExamQuestionOrderMode::Static->value
                : $data['question_order_mode'];
            $questionCount = $selectionMode === ExamQuestionSelectionMode::Random->value ? ($data['question_count'] ?? null) : null;

            if ($creating) {
                $exam = Exam::create([
                    'course_id' => $course->getKey(),
                    'name' => trim($data['name']),
                    'code' => filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : null,
                    'description' => $data['description'] ?? null,
                    'passing_score' => $data['passing_score'] ?? null,
                    'retake_score' => $data['retake_score'] ?? null,
                    'certificate_validity_years' => $data['certificate_validity_years'] ?? null,
                    'question_order_mode' => $questionOrderMode,
                    'question_selection_mode' => $selectionMode,
                    'question_count' => $questionCount,
                    'status' => $data['status'] ?? ExamStatus::Draft,
                    'created_by_user_id' => auth()->id(),
                    'updated_by_user_id' => auth()->id(),
                ]);
                $before = null;
            } else {
                $exam = Exam::query()->lockForUpdate()->findOrFail($exam->getKey());
                $before = ['name' => $exam->name, 'status' => $exam->status->value, 'question_order_mode' => $exam->question_order_mode->value, 'question_selection_mode' => $exam->question_selection_mode?->value ?? ExamQuestionSelectionMode::Manual->value, 'question_count' => $exam->question_count];
                $exam->update([
                    'name' => trim($data['name']),
                    // The Exam Code is generated once on creation and must stay
                    // stable across ordinary edits: only overwrite it here if the
                    // caller explicitly supplied a new one, never null it out
                    // just because an update payload omitted the field.
                    'code' => filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : $exam->code,
                    'description' => $data['description'] ?? null,
                    'passing_score' => $data['passing_score'] ?? null,
                    'retake_score' => $data['retake_score'] ?? null,
                    'certificate_validity_years' => $data['certificate_validity_years'] ?? null,
                    'question_order_mode' => $questionOrderMode,
                    'question_selection_mode' => $selectionMode,
                    'question_count' => $questionCount,
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
                'question_selection_mode' => $exam->question_selection_mode?->value ?? ExamQuestionSelectionMode::Manual->value,
                'random_question_count' => $exam->question_count,
            ]);
            $this->audit->record('exam.questions_updated', $exam, null, ['exam_id' => $exam->getKey(), 'question_count' => $exam->examQuestions->count()]);

            $this->scheduleIfNeeded($exam, $data);

            return $exam->fresh(['subject', 'examQuestions.question', 'groups', 'schedules']);
        });
    }

    /**
     * Publishing an Exam for the first time bundles it with its initial Group
     * schedule in the same admin action, so the Class exists and is visible to
     * Proctor/Instructor/Student immediately, with no separate "Create Exam
     * Schedule" step required. Exams that already have a schedule are left
     * alone here; additional Group schedules still go through SaveExamScheduleAction
     * directly (admin.exam-schedules.*) so one Exam can still serve multiple Groups.
     */
    private function scheduleIfNeeded(Exam $exam, array $data): void
    {
        if ($exam->status !== ExamStatus::Published || $exam->schedules()->exists()) {
            return;
        }
        if (! filled($data['group_id'] ?? null) || ! filled($data['start_date'] ?? null) || ! filled($data['end_date'] ?? null)) {
            return;
        }

        $this->scheduler->execute(null, [
            'exam_id' => $exam->getKey(),
            'group_id' => $data['group_id'],
            'training_provider_id' => $data['training_provider_id'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'start_mode' => $data['start_mode'] ?? 'automatic',
            'proctor_id' => $data['proctor_id'] ?? null,
            'instructor_id' => $data['instructor_id'] ?? null,
        ]);
    }

    private function syncQuestions(Exam $exam, array $data): void
    {
        if ($exam->question_selection_mode === ExamQuestionSelectionMode::Random) {
            $exam->examQuestions()->delete();

            return;
        }

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
