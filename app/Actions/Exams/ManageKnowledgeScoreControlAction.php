<?php

namespace App\Actions\Exams;

use App\Enums\KnowledgeControlType;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamAttemptScoreControl;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\KnowledgeResultService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageKnowledgeScoreControlAction
{
    public function __construct(private readonly AuditRecorder $audit, private readonly KnowledgeResultService $results) {}

    public function create(ExamAttempt $attempt, KnowledgeControlType $type, array $data, User $admin): ExamAttemptScoreControl
    {
        return DB::transaction(function () use ($attempt, $type, $data, $admin): ExamAttemptScoreControl {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $question = isset($data['exam_attempt_question_id'])
                ? ExamAttemptQuestion::query()->where('exam_attempt_id', $attempt->id)->findOrFail($data['exam_attempt_question_id'])
                : null;

            if ($type === KnowledgeControlType::QuestionPoints && (float) $data['numeric_value'] > (float) ($question?->points ?? 0)) {
                throw ValidationException::withMessages(['numeric_value' => 'Awarded points cannot exceed this question\'s possible points.']);
            }

            if (in_array($type, [KnowledgeControlType::FinalScore, KnowledgeControlType::PassFail, KnowledgeControlType::QuestionPoints], true)) {
                $query = ExamAttemptScoreControl::query()->where('exam_attempt_id', $attempt->id)->where('type', $type)->whereNull('reverted_at');
                if ($type === KnowledgeControlType::QuestionPoints) {
                    $query->where('exam_attempt_question_id', $question?->id);
                }
                if ($query->exists()) {
                    throw ValidationException::withMessages(['type' => 'Revert the active control of this type before adding another.']);
                }
            }

            $before = $this->resultState($this->results->resolve($attempt));
            $control = ExamAttemptScoreControl::create([
                'exam_attempt_id' => $attempt->id,
                'exam_attempt_question_id' => $question?->id,
                'type' => $type,
                'numeric_value' => $data['numeric_value'] ?? null,
                'boolean_value' => $data['boolean_value'] ?? null,
                'before_state' => $before,
                'after_state' => $before,
                'reason' => $data['reason'],
                'created_by_user_id' => $admin->id,
            ]);
            $attempt->unsetRelation('scoreControls');
            $after = $this->resultState($this->results->resolve($attempt));
            $control->update(['after_state' => $after]);
            $this->audit->record('exam_attempt.knowledge_control_added', $attempt, $before, $after, $data['reason'], $admin->id);

            return $control;
        });
    }

    public function revert(ExamAttempt $attempt, ExamAttemptScoreControl $control, string $reason, User $admin): void
    {
        DB::transaction(function () use ($attempt, $control, $reason, $admin): void {
            abort_unless($control->exam_attempt_id === $attempt->id, 404);
            $locked = ExamAttemptScoreControl::query()->lockForUpdate()->findOrFail($control->id);
            if ($locked->reverted_at) {
                throw ValidationException::withMessages(['control' => 'This control has already been reverted.']);
            }
            $before = $this->resultState($this->results->resolve($attempt));
            $locked->update(['reverted_at' => now(), 'reverted_by_user_id' => $admin->id, 'revert_reason' => $reason]);
            $attempt->unsetRelation('scoreControls');
            $after = $this->resultState($this->results->resolve($attempt));
            $this->audit->record('exam_attempt.knowledge_control_reverted', $attempt, $before, $after, $reason, $admin->id);
        });
    }

    public function restore(ExamAttempt $attempt, string $reason, User $admin): int
    {
        return DB::transaction(function () use ($attempt, $reason, $admin): int {
            $before = $this->resultState($this->results->resolve($attempt));
            $controls = ExamAttemptScoreControl::query()->where('exam_attempt_id', $attempt->id)->whereNull('reverted_at')->lockForUpdate()->get();
            foreach ($controls as $control) {
                $control->update(['reverted_at' => now(), 'reverted_by_user_id' => $admin->id, 'revert_reason' => $reason]);
            }
            $attempt->unsetRelation('scoreControls');
            $after = $this->resultState($this->results->resolve($attempt));
            $this->audit->record('exam_attempt.knowledge_controls_restored', $attempt, $before, $after, $reason, $admin->id);

            return $controls->count();
        });
    }

    /** @return array<string, mixed> */
    private function resultState(array $result): array
    {
        return [
            'final_knowledge_score' => $result['final_knowledge_score'],
            'final_passed' => $result['final_passed'],
            'certificate_eligible' => $result['certificate_eligible'],
            'active_control_count' => count($result['active_overrides']),
        ];
    }
}
