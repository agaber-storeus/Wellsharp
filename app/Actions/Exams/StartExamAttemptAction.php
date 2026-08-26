<?php

namespace App\Actions\Exams;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamQuestionSelectionMode;
use App\Enums\ExamScheduleStatus;
use App\Enums\GroupMembershipStatus;
use App\Enums\QuestionType;
use App\Enums\TranslationStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\TranslationLanguage;
use App\Models\User;
use App\Services\Translation\QuestionTranslationService;
use App\Services\Translation\TranslationProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartExamAttemptAction
{
    public function __construct(
        private readonly QuestionTranslationService $translationService,
        private readonly TranslationProviderInterface $provider,
    ) {}

    public function execute(ExamSchedule $schedule, User $student, ?string $languageCode = null): ExamAttempt
    {
        return DB::transaction(function () use ($schedule, $student, $languageCode): ExamAttempt {
            $schedule = ExamSchedule::query()->with('exam')->lockForUpdate()->findOrFail($schedule->getKey());
            $this->assertStudentCanStart($schedule, $student);
            $language = $this->resolveLanguage($languageCode);

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

            $examQuestions = $this->questionAssignments($schedule->exam);

            if ($examQuestions->isEmpty()) {
                throw ValidationException::withMessages(['exam' => 'This exam does not contain any questions.']);
            }

            $attempt = ExamAttempt::create([
                'exam_id' => $schedule->exam_id,
                'exam_schedule_id' => $schedule->getKey(),
                'student_user_id' => $student->getKey(),
                'language_code' => $language?->code,
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

            // Translation is resolved only for the Questions actually selected for
            // THIS attempt (post random-selection, post-shuffle) - never the whole
            // Exam/Question bank. Reused across every other Exam/attempt that shares
            // the same Question+language+source text via QuestionTranslationService's
            // own cache; a provider call only happens for what's missing/stale.
            $translations = $language
                ? $this->translationService->resolveForQuestions($orderedQuestions->pluck('question'), $language)
                : [];

            foreach ($orderedQuestions as $index => $assignment) {
                $question = $assignment['question'];
                $translation = $translations[$question->getKey()] ?? null;

                ExamAttemptQuestion::create([
                    'exam_attempt_id' => $attempt->getKey(),
                    'question_id' => $question->getKey(),
                    'display_order' => $index + 1,
                    'points' => $assignment['points'],
                    'option_order' => $this->optionOrder($question, $orderMode),
                    'translated_question_text' => $translation['question_text'] ?? null,
                    'translated_options' => $translation['options'] ?? null,
                    'question_translation_status' => $translation === null ? null : ($translation['translated']
                        ? TranslationStatus::Translated
                        : TranslationStatus::Failed),
                ]);
            }

            return $attempt->fresh(['exam', 'schedule', 'attemptQuestions.question']);
        });
    }

    /**
     * Null means the original source language - always available with no
     * provider dependency. A non-blank code must currently be an Admin-
     * enabled language for the active provider; anything else is rejected
     * here rather than silently falling back, so a stale/tampered dropdown
     * value never starts an attempt in a language nobody approved.
     */
    private function resolveLanguage(?string $languageCode): ?TranslationLanguage
    {
        if (blank($languageCode)) {
            return null;
        }

        $language = TranslationLanguage::query()
            ->where('provider', $this->provider->name())
            ->where('code', $languageCode)
            ->where('is_enabled', true)
            ->first();

        if (! $language) {
            throw ValidationException::withMessages(['language_code' => 'The selected exam language is not available.']);
        }

        return $language;
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

    /** @return Collection<int, array{question: Question, points: mixed}> */
    private function questionAssignments(Exam $exam): Collection
    {
        if ($exam->question_selection_mode === ExamQuestionSelectionMode::Random) {
            $count = (int) $exam->question_count;
            $questions = Question::query()
                ->where('course_id', $exam->course_id)
                ->where('is_active', true)
                ->with('options')
                ->inRandomOrder()
                ->limit($count)
                ->get();

            if ($count < 1 || $questions->count() !== $count) {
                throw ValidationException::withMessages(['exam' => 'This exam does not have enough active questions for its configured random question count.']);
            }

            return $questions->map(fn (Question $question): array => [
                'question' => $question,
                'points' => $question->default_marks,
            ])->values();
        }

        return ExamQuestion::query()
            ->where('exam_id', $exam->getKey())
            ->with('question.options')
            ->orderBy('display_order')
            ->get()
            ->map(fn (ExamQuestion $examQuestion): array => [
                'question' => $examQuestion->question,
                'points' => $examQuestion->points,
            ])->values();
    }

    /** @param Collection<int, array{question: Question, points: mixed}> $examQuestions */
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
