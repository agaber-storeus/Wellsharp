<?php

namespace App\Enums;

enum KnowledgeControlType: string
{
    case QuestionPoints = 'question_points';
    case ScoreAdjustment = 'score_adjustment';
    case FinalScore = 'final_score';
    case PassFail = 'pass_fail';

    public function label(): string
    {
        return match ($this) {
            self::QuestionPoints => 'Question points override',
            self::ScoreAdjustment => 'Score adjustment',
            self::FinalScore => 'Final score override',
            self::PassFail => 'Pass / Fail override',
        };
    }
}
