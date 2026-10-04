<?php

namespace App\Services;

use App\Enums\KnowledgeControlType;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptScoreControl;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class KnowledgeResultService
{
    public function __construct(private readonly ExamScoringService $scoring) {}

    /** @return array<string, mixed> */
    public function resolve(ExamAttempt $attempt): array
    {
        $attempt->loadMissing([
            'exam',
            'attemptQuestions.question.options',
            'scoreControls.createdBy.profile',
            'scoreControls.revertedBy.profile',
            'scoreControls.attemptQuestion.question',
        ]);

        $calculatedScore = $attempt->score === null ? null : (float) $attempt->score;
        $passingScore = (int) ($attempt->exam?->passing_score ?? 0);
        $controls = $attempt->scoreControls;
        $active = $controls->whereNull('reverted_at');
        $breakdown = $calculatedScore === null ? [] : $this->scoring->breakdown($attempt);
        $breakdownById = collect($breakdown)->keyBy('id');

        $possiblePoints = (float) collect($breakdown)->sum('points');
        $questionPointDelta = 0.0;
        $questionOverrides = $active->where('type', KnowledgeControlType::QuestionPoints);
        foreach ($questionOverrides as $control) {
            $original = $breakdownById->get($control->exam_attempt_question_id);
            if ($original) {
                $questionPointDelta += (float) $control->numeric_value - (float) $original['earned_points'];
            }
        }

        $questionAdjustedScore = $calculatedScore;
        if ($calculatedScore !== null && $possiblePoints > 0 && $questionOverrides->isNotEmpty()) {
            $questionAdjustedScore = round(max(0, min(100, $calculatedScore + ($questionPointDelta / $possiblePoints * 100))), 2);
        }

        $adjustmentTotal = round((float) $active
            ->where('type', KnowledgeControlType::ScoreAdjustment)
            ->sum(fn (ExamAttemptScoreControl $control): float => (float) $control->numeric_value), 2);
        $scoreBeforeFinalOverride = $questionAdjustedScore === null
            ? null
            : round(max(0, min(100, $questionAdjustedScore + $adjustmentTotal)), 2);

        $finalScoreControl = $active->where('type', KnowledgeControlType::FinalScore)->last();
        $finalScoreOverride = $finalScoreControl ? (float) $finalScoreControl->numeric_value : null;
        $finalKnowledgeScore = $finalScoreOverride ?? $scoreBeforeFinalOverride;
        $calculatedPassed = $calculatedScore === null ? null : $calculatedScore >= $passingScore;
        $scorePassed = $finalKnowledgeScore === null ? null : $finalKnowledgeScore >= $passingScore;
        $passFailControl = $active->where('type', KnowledgeControlType::PassFail)->last();
        $finalPassed = $passFailControl ? (bool) $passFailControl->boolean_value : $scorePassed;

        $activeRows = $active->map(fn (ExamAttemptScoreControl $control): array => $this->controlData($control))->values()->all();

        return [
            'calculated_score' => $calculatedScore,
            'question_adjusted_score' => $questionAdjustedScore,
            'adjustment_total' => $adjustmentTotal,
            'score_before_final_override' => $scoreBeforeFinalOverride,
            'final_score_override' => $finalScoreOverride,
            'final_knowledge_score' => $finalKnowledgeScore,
            'passing_score' => $passingScore,
            'calculated_passed' => $calculatedPassed,
            'final_passed' => $finalPassed,
            'certificate_eligible' => $attempt->status->value === 'submitted' && $finalPassed === true,
            'pass_fail_override' => $passFailControl?->boolean_value,
            'active_overrides' => $activeRows,
            'override_history' => $controls->sortByDesc('id')->map(fn (ExamAttemptScoreControl $control): array => $this->controlData($control))->values()->all(),
            'breakdown' => collect($breakdown)->map(function (array $row) use ($questionOverrides): array {
                $control = $questionOverrides->firstWhere('exam_attempt_question_id', $row['id']);
                $row['awarded_points'] = $control ? (float) $control->numeric_value : (float) $row['earned_points'];
                $row['points_overridden'] = $control !== null;
                $row['points_control_id'] = $control?->public_id;

                return $row;
            })->all(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function resolveMany(Collection $attempts): Collection
    {
        if ($attempts->isEmpty()) {
            return collect();
        }

        (new EloquentCollection($attempts->all()))->loadMissing(['exam', 'attemptQuestions.question.options', 'scoreControls.createdBy.profile', 'scoreControls.revertedBy.profile', 'scoreControls.attemptQuestion.question']);

        return $attempts->mapWithKeys(fn (ExamAttempt $attempt): array => [$attempt->getKey() => $this->resolve($attempt)]);
    }

    /** @return array<string, mixed> */
    private function controlData(ExamAttemptScoreControl $control): array
    {
        return [
            'id' => $control->public_id,
            'type' => $control->type->value,
            'type_label' => $control->type->label(),
            'numeric_value' => $control->numeric_value === null ? null : (float) $control->numeric_value,
            'boolean_value' => $control->boolean_value,
            'question_id' => $control->attemptQuestion?->id,
            'question_code' => $control->attemptQuestion?->question?->code,
            'reason' => $control->reason,
            'before_state' => $control->before_state,
            'after_state' => $control->after_state,
            'created_by' => $control->createdBy?->display_name,
            'created_at' => $control->created_at?->format('Y-m-d H:i'),
            'reverted_at' => $control->reverted_at?->format('Y-m-d H:i'),
            'reverted_by' => $control->revertedBy?->display_name,
            'revert_reason' => $control->revert_reason,
            'active' => $control->reverted_at === null,
        ];
    }
}
