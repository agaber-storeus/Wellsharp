<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\ExamAttempt;

/**
 * The single source of truth for "what score actually counts" and "did the
 * trainee pass" wherever those questions matter for certification purposes
 * (certificate eligibility/issuance, dashboards, reports). `resolve()` is the
 * one canonical formula every consumer ultimately goes through:
 *
 *   effective score = skills_score if set, otherwise the Knowledge Exam score
 *   passed = effective score >= passing score
 *
 * Skills Score is a manual override of the trainee's final percentage, not a
 * second/parallel score - see BR-notes on `enrollments.skills_score`. This
 * service never writes anything; it only reads `exam_attempts.score` (the
 * automated Knowledge Exam result, which this override never touches) and
 * `enrollments.skills_score` (the override, if any) and combines them.
 */
class EffectiveScoreService
{
    /**
     * @return array{knowledge_score: float, skills_score: ?int, score: float, passed: bool, overridden: bool}
     */
    public function resolve(float $knowledgeScore, ?int $skillsScore, int $passingScore): array
    {
        $effectiveScore = $skillsScore !== null ? (float) $skillsScore : $knowledgeScore;

        return [
            'knowledge_score' => $knowledgeScore,
            'skills_score' => $skillsScore,
            'score' => $effectiveScore,
            'passed' => $effectiveScore >= $passingScore,
            'overridden' => $skillsScore !== null,
        ];
    }

    /**
     * Convenience for callers that only have an ExamAttempt in hand (no
     * Enrollment loaded yet) - resolves the correlated Enrollment via
     * enrollmentFor() and applies resolve(). Costs one extra query; callers
     * that already have the Enrollment (e.g. building a Class roster where
     * both are already eager-loaded) should call resolve() directly instead.
     *
     * @return array{knowledge_score: float, skills_score: ?int, score: float, passed: bool, overridden: bool}
     */
    public function forAttempt(ExamAttempt $attempt): array
    {
        $attempt->loadMissing(['exam', 'schedule']);

        return $this->resolve(
            (float) ($attempt->score ?? 0),
            $this->enrollmentFor($attempt)?->skills_score,
            (int) ($attempt->exam?->passing_score ?? 0),
        );
    }

    /**
     * There is no foreign key between `exam_attempts` and `enrollments` -
     * both are keyed independently by (Class, Student). This mirrors the
     * correlation OperationalClassMapPointBuilder already uses for the Class
     * Dashboard roster: an attempt's Class comes from its Schedule.
     */
    public function enrollmentFor(ExamAttempt $attempt): ?Enrollment
    {
        $classId = $attempt->schedule?->training_class_id;
        if (! $classId) {
            return null;
        }

        return Enrollment::query()
            ->where('class_id', $classId)
            ->where('student_user_id', $attempt->student_user_id)
            ->first();
    }

    /**
     * The reverse correlation: given an Enrollment, find the attempt whose
     * result it overrides. Same "latest attempt_number wins" rule already
     * used by OperationalClassMapPointBuilder for the roster's Knowledge
     * Exam column, so a retake's own certificate/eligibility decision stays
     * consistent with what the roster displays as "the" attempt.
     */
    public function latestAttemptFor(Enrollment $enrollment): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->whereHas('schedule', fn ($query) => $query->where('training_class_id', $enrollment->class_id))
            ->where('student_user_id', $enrollment->student_user_id)
            ->orderByDesc('attempt_number')
            ->first();
    }
}
