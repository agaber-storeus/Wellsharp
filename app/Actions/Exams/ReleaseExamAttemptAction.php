<?php

namespace App\Actions\Exams;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\ExamScoringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReleaseExamAttemptAction
{
    public function __construct(
        private readonly ExamScoringService $scoring,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(ExamAttempt $attempt, User $actor): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $actor): ExamAttempt {
            $locked = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            if ($locked->released_at) {
                return $locked->fresh(['exam', 'schedule', 'student.profile']);
            }

            if (! in_array($locked->status, [ExamAttemptStatus::InProgress, ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired], true)) {
                throw ValidationException::withMessages(['attempt' => 'This trainee exam cannot be released from its current state.']);
            }

            $before = $locked->toArray();
            if ($locked->status === ExamAttemptStatus::InProgress) {
                $locked->status = ExamAttemptStatus::Submitted;
                $locked->submitted_at ??= now();
            }

            $result = $this->scoring->calculate($locked->fresh(['exam', 'attemptQuestions.question.options']));
            $locked->forceFill([
                'score' => $result['score'],
                'passed' => $result['passed'],
                'scored_at' => now(),
                'released_at' => now(),
                'released_by_user_id' => $actor->getKey(),
            ])->save();
            $this->audit->record('exam_attempt.released', $locked, $before, $locked->fresh()->toArray(), 'Released by '.$actor->display_name);

            return $locked->fresh(['exam', 'schedule', 'student.profile', 'releasedBy.profile']);
        });
    }
}
