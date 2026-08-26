<?php

namespace App\Actions\Exams;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\ExamScoringService;
use App\Services\Translation\AnswerBackTranslationResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitExamAttemptAction
{
    public function __construct(
        private readonly ExamScoringService $scoring,
        private readonly AnswerBackTranslationResolver $backTranslation,
    ) {}

    public function execute(ExamAttempt $attempt, User $student): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $student): ExamAttempt {
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            abort_unless($attempt->student_user_id === $student->getKey(), 403);

            if ($attempt->status !== ExamAttemptStatus::InProgress) {
                throw ValidationException::withMessages(['exam' => 'This exam attempt is no longer available.']);
            }

            if ($attempt->expires_at?->isPast()) {
                $attempt->update(['status' => ExamAttemptStatus::Expired]);
                throw ValidationException::withMessages(['exam' => 'This exam attempt has expired.']);
            }

            $attempt->update(['status' => ExamAttemptStatus::Submitted, 'submitted_at' => now()]);

            $scored = $attempt->fresh(['exam', 'attemptQuestions.question.options']);
            // Best-effort: a provider failure here never blocks submission or marks a
            // Text Input answer wrong (see ExamScoringService::correctness()) - it is
            // simply retried the next time scoring runs (BR-027's recompute at
            // certificate issuance, via the same resolver call there).
            $this->backTranslation->resolve($scored);
            $result = $this->scoring->calculate($scored->fresh(['exam', 'attemptQuestions.question.options']));
            $attempt->update(['score' => $result['score'], 'passed' => $result['passed'], 'scored_at' => now()]);

            return $attempt->fresh(['exam', 'schedule', 'attemptQuestions.question.options']);
        });
    }
}
