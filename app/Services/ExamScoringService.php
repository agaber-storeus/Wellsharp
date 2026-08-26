<?php

namespace App\Services;

use App\Enums\TranslationStatus;
use App\Enums\QuestionType;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\Question;
use Illuminate\Support\Facades\Storage;

class ExamScoringService
{
    /** @return array{score: float, passed: bool} */
    public function calculate(ExamAttempt $attempt): array
    {
        $breakdown = $this->breakdown($attempt);
        $possiblePoints = 0.0;
        $earnedPoints = 0.0;

        foreach ($breakdown as $question) {
            if ($question['translation_pending']) {
                // Back-translation for this Text Input answer has not succeeded yet
                // (see AnswerTranslationResolver) - excluded from both sides of the
                // score entirely rather than ever counted wrong. It is retried the
                // next time calculate() runs (submit, then again at certificate
                // issuance - BR-027's existing recompute-on-issuance is the retry).
                continue;
            }

            $points = $question['points'];
            $possiblePoints += $points;
            $earnedPoints += $question['earned_points'];
        }

        $score = $possiblePoints > 0 ? round(($earnedPoints / $possiblePoints) * 100, 2) : 0.0;
        $passingScore = (int) ($attempt->exam->passing_score ?? 0);

        return [
            'score' => $score,
            'passed' => $score >= $passingScore,
            'possible_points' => round($possiblePoints, 2),
            'earned_points' => round($earnedPoints, 2),
            'question_count' => count($breakdown),
            'correct_count' => collect($breakdown)->where('is_correct', true)->count(),
            'incorrect_count' => collect($breakdown)->where('is_correct', false)->where('answered', true)->count(),
            'unanswered_count' => collect($breakdown)->where('answered', false)->count(),
            'translation_pending_count' => collect($breakdown)->where('translation_pending', true)->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function breakdown(ExamAttempt $attempt): array
    {
        $attempt->loadMissing(['exam', 'attemptQuestions.question.options']);

        return $attempt->attemptQuestions->map(function (ExamAttemptQuestion $attemptQuestion) use ($attempt): array {
            $answer = $attemptQuestion->answer;
            $isCorrect = $this->correctness($attempt, $attemptQuestion);
            $points = (float) ($attemptQuestion->points ?? $attemptQuestion->question->default_marks ?? 1);
            $question = $attemptQuestion->question;
            $selectedOption = $question->type === QuestionType::Mcq
                ? $question->options->firstWhere('public_id', $answer)
                : null;
            $correctOption = $question->type === QuestionType::Mcq
                ? $question->options->firstWhere('is_correct', true)
                : null;

            return [
                'id' => $attemptQuestion->getKey(),
                'display_order' => $attemptQuestion->display_order,
                'question_id' => $attemptQuestion->question->public_id,
                'question_text' => $question->question_text,
                'question_image_url' => $this->imageUrl($question->question_image_path),
                'type' => $question->type->value,
                'answer' => $this->answerLabel($question, $answer),
                'answer_image_url' => $this->imageUrl($selectedOption?->image_path),
                'raw_answer' => $answer,
                'correct_answer' => $this->correctAnswerLabel($question),
                'correct_answer_image_url' => $this->imageUrl($correctOption?->image_path ?: $question->correct_answer_image_path),
                'answered' => filled($answer),
                'is_correct' => $isCorrect === true,
                'translation_pending' => $isCorrect === null,
                'points' => $points,
                'earned_points' => $isCorrect === true ? $points : 0.0,
            ];
        })->values()->all();
    }

    private function answerLabel(Question $question, ?string $answer): ?string
    {
        if (blank($answer)) {
            return null;
        }

        if ($question->type === QuestionType::Mcq) {
            return $question->options->firstWhere('public_id', $answer)?->option_text ?: $answer;
        }

        return $answer;
    }

    private function correctAnswerLabel(Question $question): ?string
    {
        return match ($question->type) {
            QuestionType::Mcq => $question->options->firstWhere('is_correct', true)?->option_text,
            QuestionType::TrueFalse => $question->correct_answer_boolean === null ? null : ($question->correct_answer_boolean ? 'True' : 'False'),
            QuestionType::Input => $question->correct_answer_text,
        };
    }

    /**
     * True/false as before for every question type except a translated
     * attempt's Text Input answers, which can also come back null - meaning
     * "not yet gradable" (back-translation hasn't succeeded), never
     * "incorrect". MCQ/True-False identity and scoring are completely
     * unaffected by translation: MCQ always compares the submitted
     * option's public_id, never translated option text.
     */
    private function correctness(ExamAttempt $attempt, ExamAttemptQuestion $attemptQuestion): ?bool
    {
        $question = $attemptQuestion->question;
        $answer = $attemptQuestion->answer;

        if ($answer === null || $answer === '') {
            return false;
        }

        if ($question->type === QuestionType::Input && $attempt->isTranslated()) {
            if ($attemptQuestion->answer_translation_status !== TranslationStatus::Translated) {
                return null;
            }

            return Question::normalizeText($attemptQuestion->back_translated_answer) === Question::normalizeText($question->correct_answer_text);
        }

        return match ($question->type) {
            QuestionType::Mcq => $question->options->contains(fn ($option): bool => $option->is_correct && (string) $option->public_id === (string) $answer),
            QuestionType::TrueFalse => strtolower($answer) === ($question->correct_answer_boolean ? 'true' : 'false'),
            QuestionType::Input => Question::normalizeText($answer) === Question::normalizeText($question->correct_answer_text),
        };
    }

    private function imageUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }
}
