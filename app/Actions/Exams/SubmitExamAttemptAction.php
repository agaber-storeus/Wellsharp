<?php

namespace App\Actions\Exams;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\ExamScoringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitExamAttemptAction
{
    public function __construct(private readonly ExamScoringService $scoring) {}

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
            $result = $this->scoring->calculate($attempt->fresh(['exam', 'attemptQuestions.question.options']));
            $attempt->update(['score' => $result['score'], 'passed' => $result['passed'], 'scored_at' => now()]);

            return $attempt->fresh(['exam', 'schedule', 'attemptQuestions.question.options']);
        });
    }
}
