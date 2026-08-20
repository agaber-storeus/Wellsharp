<?php

namespace App\Actions\Exams;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamScheduleStatus;
use App\Enums\GroupMembershipStatus;
use App\Enums\QuestionType;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartExamAttemptAction
{
    public function execute(ExamSchedule $schedule, User $student): ExamAttempt
    {
        return DB::transaction(function () use ($schedule, $student): ExamAttempt {
            $schedule = ExamSchedule::query()->with('exam')->lockForUpdate()->findOrFail($schedule->getKey());
            $this->assertStudentCanStart($schedule, $student);

            $finished = ExamAttempt::query()
                ->where('exam_schedule_id', $schedule->getKey())
                ->where('student_user_id', $student->getKey())
                ->where('status', ExamAttemptStatus::Submitted->value)
                ->lockForUpdate()
                ->exists();

            if ($finished) {
                throw ValidationException::withMessages([
                    'exam' => 'You have already finished this exam. A second attempt is not available.',
                ]);
            }

            $existing = ExamAttempt::query()
                ->where('exam_schedule_id', $schedule->getKey())
                ->where('student_user_id', $student->getKey())
                ->where('status', ExamAttemptStatus::InProgress->value)
                ->lockForUpdate()
                ->first();

            if ($existing && (! $existing->expires_at || $existing->expires_at->isFuture())) {
                return $existing->fresh(['exam', 'schedule', 'attemptQuestions.question']);
            }

            if ($existing) {
                $existing->update(['status' => ExamAttemptStatus::Expired]);
            }

            $examQuestions = ExamQuestion::query()
                ->where('exam_id', $schedule->exam_id)
                ->with('question.options')
                ->orderBy('display_order')
                ->get();

            if ($examQuestions->isEmpty()) {
                throw ValidationException::withMessages(['exam' => 'This exam does not contain any questions.']);
            }

            $attempt = ExamAttempt::create([
                'exam_id' => $schedule->exam_id,
                'exam_schedule_id' => $schedule->getKey(),
                'student_user_id' => $student->getKey(),
                'attempt_number' => ((int) ExamAttempt::query()
                    ->where('exam_schedule_id', $schedule->getKey())
                    ->where('student_user_id', $student->getKey())
                    ->max('attempt_number')) + 1,
                'status' => ExamAttemptStatus::InProgress,
                'started_at' => now(),
                'expires_at' => $this->expiresAt($schedule),
            ]);

            $orderMode = $schedule->exam->question_order_mode;
            $orderedQuestions = $this->orderQuestions($examQuestions, $orderMode);
            foreach ($orderedQuestions as $index => $examQuestion) {
                ExamAttemptQuestion::create([
                    'exam_attempt_id' => $attempt->getKey(),
                    'question_id' => $examQuestion->question_id,
                    'display_order' => $index + 1,
                    'points' => $examQuestion->points,
                    'option_order' => $this->optionOrder($examQuestion->question, $orderMode),
                ]);
            }

            return $attempt->fresh(['exam', 'schedule', 'attemptQuestions.question']);
        });
    }

    private function assertStudentCanStart(ExamSchedule $schedule, User $student): void
    {
        if ($schedule->status !== ExamScheduleStatus::Scheduled) {
            throw ValidationException::withMessages(['exam' => 'This exam schedule is not available.']);
        }

        if (! $schedule->group_id || ! $student->groupMemberships()
            ->where('group_id', $schedule->group_id)
            ->where('status', GroupMembershipStatus::Active->value)
            ->exists()) {
            abort(403, 'You are not assigned to this exam group.');
        }

        if (! $schedule->override_started_at && $schedule->start_date?->isFuture()) {
            throw ValidationException::withMessages([
                'exam' => 'This exam is available starting '.$schedule->start_date->format('F j, Y').'.',
            ]);
        }

        if (! $schedule->override_started_at && $schedule->end_date?->endOfDay()->isPast()) {
            throw ValidationException::withMessages(['exam' => 'This exam schedule has ended.']);
        }
    }

    private function orderQuestions(Collection $examQuestions, ExamQuestionOrderMode $mode): Collection
    {
        if ($mode === ExamQuestionOrderMode::Shuffle && $examQuestions->count() > 1) {
            return $examQuestions->shuffle()->values();
        }

        return $examQuestions->values();
    }

    /**
     * Freezes a per-student randomized MCQ option order when the exam shuffles question order.
     * Static exams, and non-MCQ questions, keep the option bank's natural display order (null).
     */
    private function optionOrder(Question $question, ExamQuestionOrderMode $mode): ?array
    {
        if ($mode !== ExamQuestionOrderMode::Shuffle || $question->type !== QuestionType::Mcq || $question->options->count() < 2) {
            return null;
        }

        return $question->options->shuffle()->pluck('public_id')->all();
    }

    private function expiresAt(ExamSchedule $schedule): ?Carbon
    {
        return $schedule->duration_minutes ? now()->addMinutes($schedule->duration_minutes) : null;
    }
}
