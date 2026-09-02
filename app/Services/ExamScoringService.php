<?php

namespace App\Services;

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
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function breakdown(ExamAttempt $attempt): array
    {
        $attempt->loadMissing(['exam', 'attemptQuestions.question.options']);

        return $attempt->attemptQuestions->map(function (ExamAttemptQuestion $attemptQuestion): array {
            $answer = $attemptQuestion->answer;
            $isCorrect = $this->isCorrect($attemptQuestion->question, $answer);
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
                'question_text' => $question->display_question_text,
                'question_image_url' => $this->imageUrl($question->question_image_path),
                'type' => $question->type->value,
                'answer' => $this->answerLabel($question, $answer),
                'answer_image_url' => $this->imageUrl($selectedOption?->image_path),
                'raw_answer' => $answer,
                'correct_answer' => $this->correctAnswerLabel($question),
                'correct_answer_image_url' => $this->imageUrl($correctOption?->image_path ?: $question->correct_answer_image_path),
                'answered' => filled($answer),
                'is_correct' => $isCorrect,
                'points' => $points,
                'earned_points' => $isCorrect ? $points : 0.0,
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

    private function isCorrect(Question $question, ?string $answer): bool
    {
        if ($answer === null || $answer === '') {
            return false;
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
