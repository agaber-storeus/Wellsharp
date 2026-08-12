<?php

namespace App\Http\Controllers\Student;

use App\Actions\Certificates\IssueCertificateAction;
use App\Actions\Exams\StartExamAttemptAction;
use App\Actions\Exams\SubmitExamAttemptAction;
use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamSchedule;
use App\Services\StudentExamFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExamAttemptController extends Controller
{
    public function start(ExamSchedule $schedule, StartExamAttemptAction $action, StudentExamFlowService $flow): RedirectResponse
    {
        $flow->assertReadyForExam($schedule, auth()->user());
        $attempt = $action->execute($schedule, auth()->user());

        return redirect()->route('student.attempts.show', $attempt);
    }

    public function show(ExamAttempt $attempt): View|RedirectResponse
    {
        abort_unless($attempt->student_user_id === auth()->id(), 403);

        if ($attempt->status !== ExamAttemptStatus::InProgress) {
            return redirect()->route('student.dashboard')->with('status', $attempt->status === ExamAttemptStatus::Expired
                ? 'This exam attempt expired and can no longer be continued.'
                : 'This exam attempt has already been submitted.');
        }

        if ($attempt->expires_at?->isPast()) {
            $attempt->update(['status' => ExamAttemptStatus::Expired]);

            return redirect()->route('student.dashboard')->with('status', 'This exam attempt expired and can no longer be continued.');
        }

        $attempt->load(['exam.subject', 'schedule.group', 'attemptQuestions.question.options']);

        $initialAnswers = $attempt->attemptQuestions->mapWithKeys(fn (ExamAttemptQuestion $attemptQuestion): array => [
            (string) $attemptQuestion->getKey() => $attemptQuestion->answer,
        ]);

        return view('student.exam', compact('attempt', 'initialAnswers'));
    }

    public function expire(ExamAttempt $attempt): JsonResponse
    {
        abort_unless($attempt->student_user_id === auth()->id(), 403);

        $expired = DB::transaction(function () use ($attempt): bool {
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());

            if ($lockedAttempt->student_user_id !== auth()->id()) {
                abort(403);
            }

            if ($lockedAttempt->status === ExamAttemptStatus::Expired) {
                return true;
            }

            if ($lockedAttempt->status !== ExamAttemptStatus::InProgress) {
                return false;
            }

            if (! $lockedAttempt->expires_at?->isPast()) {
                throw ValidationException::withMessages(['exam' => 'This exam attempt has not expired yet.']);
            }

            $lockedAttempt->update(['status' => ExamAttemptStatus::Expired]);

            return true;
        });

        return response()->json(['expired' => $expired]);
    }

    public function submit(ExamAttempt $attempt, SubmitExamAttemptAction $action, IssueCertificateAction $issuer): RedirectResponse
    {
        abort_unless($attempt->student_user_id === auth()->id(), 403);
        $submittedAttempt = $action->execute($attempt, auth()->user());
        $issuer->execute($submittedAttempt);

        return redirect()->route('student.attempts.report', $submittedAttempt);
    }

    public function report(ExamAttempt $attempt): View
    {
        abort_unless($attempt->student_user_id === auth()->id(), 403);
        abort_unless($attempt->status === ExamAttemptStatus::Submitted, 422, 'The assessment report is not available yet.');

        return view('student.report', compact('attempt'));
    }

    public function saveAnswer(Request $request, ExamAttempt $attempt, ExamAttemptQuestion $attemptQuestion): JsonResponse
    {
        abort_unless($attempt->student_user_id === auth()->id(), 403);
        abort_unless((int) $attemptQuestion->exam_attempt_id === (int) $attempt->getKey(), 404);

        $expired = false;
        $response = DB::transaction(function () use ($request, $attempt, $attemptQuestion, &$expired): ?JsonResponse {
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            abort_unless($lockedAttempt->student_user_id === auth()->id(), 403);

            $lockedAttemptQuestion = ExamAttemptQuestion::query()
                ->with('question.options')
                ->whereKey($attemptQuestion->getKey())
                ->where('exam_attempt_id', $lockedAttempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status !== ExamAttemptStatus::InProgress) {
                throw ValidationException::withMessages(['answer' => 'This exam attempt is no longer available.']);
            }

            if ($lockedAttempt->expires_at?->isPast()) {
                $lockedAttempt->update(['status' => ExamAttemptStatus::Expired]);
                $expired = true;

                return null;
            }

            $data = $request->validate([
                'answer' => ['present', 'nullable', 'string', 'max:5000'],
            ]);
            $answer = $data['answer'] === '' ? null : $data['answer'];
            $question = $lockedAttemptQuestion->question;

            if ($answer !== null && $question->type === QuestionType::TrueFalse) {
                validator(['answer' => $answer], ['answer' => [Rule::in(['true', 'false'])]])->validate();
            }

            if ($answer !== null && $question->type === QuestionType::Mcq && ! $question->options->contains('public_id', $answer)) {
                throw ValidationException::withMessages(['answer' => 'Select a valid answer for this question.']);
            }

            $lockedAttemptQuestion->update(['answer' => $answer, 'answered_at' => $answer === null ? null : now()]);

            return response()->json([
                'saved' => true,
                'answered' => $answer !== null,
                'answered_count' => $lockedAttempt->attemptQuestions()->whereNotNull('answer')->where('answer', '<>', '')->count(),
            ]);
        });

        if ($expired) {
            throw ValidationException::withMessages(['answer' => 'This exam attempt has expired.']);
        }

        return $response;
    }
}
