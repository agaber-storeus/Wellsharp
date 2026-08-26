<?php

namespace App\Services\Translation;

use App\Enums\TranslationStatus;
use App\Enums\QuestionType;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use Illuminate\Support\Collection;

/**
 * Resolves the canonical-language back-translation needed to score a Text
 * Input answer on a translated attempt (see ExamScoringService's Input+
 * translated-attempt branch). Deliberately separate from
 * QuestionTranslationService - this translates a *Student's own answer*,
 * a different reusable-cache-vs-per-attempt-data shape entirely, and it is
 * the only place in the feature that performs an external call as a side
 * effect of scoring, so it is called explicitly (never implicitly from
 * ExamScoringService, which stays pure/IO-free for its read-only callers
 * such as report rendering).
 *
 * Idempotent and safe to call repeatedly: only answers not already
 * successfully translated are attempted, so re-running it (e.g. at
 * certificate issuance, after a submit-time provider failure) is the
 * feature's retry mechanism - no queue is needed for it.
 */
class AnswerBackTranslationResolver
{
    public function __construct(
        private readonly TranslationProviderInterface $provider,
        private readonly QuestionTranslationService $questionTranslation,
    ) {}

    public function resolve(ExamAttempt $attempt): void
    {
        if (! $attempt->isTranslated()) {
            return;
        }

        $attempt->loadMissing('attemptQuestions.question');
        $pending = $attempt->attemptQuestions
            ->filter(fn (ExamAttemptQuestion $attemptQuestion): bool => $attemptQuestion->question->type === QuestionType::Input
                && filled($attemptQuestion->answer)
                && $attemptQuestion->answer_translation_status !== TranslationStatus::Translated)
            ->values();

        if ($pending->isEmpty()) {
            return;
        }

        $this->translateBatch($attempt->language_code, $pending);
    }

    private function translateBatch(string $attemptLanguageCode, Collection $pending): void
    {
        $texts = $pending->map(fn (ExamAttemptQuestion $attemptQuestion): string => $attemptQuestion->answer)->all();

        try {
            $results = $this->provider->translate($texts, $attemptLanguageCode, $this->questionTranslation->sourceLanguage());
        } catch (TranslationProviderException) {
            // Total provider outage: leave every pending answer exactly as it was
            // (never marked incorrect - see ExamScoringService::correctness()) and
            // mark the attempt so the next resolve() call (next scoring pass) retries.
            foreach ($pending as $attemptQuestion) {
                $this->markFailed($attemptQuestion);
            }

            return;
        }

        foreach ($pending as $index => $attemptQuestion) {
            $result = $results[$index] ?? TranslationResult::failure('Provider returned no result for this item.');

            if ($result->success) {
                $attemptQuestion->update([
                    'back_translated_answer' => $result->text,
                    'answer_translation_status' => TranslationStatus::Translated,
                    'answer_translation_provider' => $this->provider->name(),
                    'answer_translated_at' => now(),
                ]);
            } else {
                $this->markFailed($attemptQuestion);
            }
        }
    }

    private function markFailed(ExamAttemptQuestion $attemptQuestion): void
    {
        $attemptQuestion->update([
            'answer_translation_status' => TranslationStatus::Failed,
            'answer_translation_provider' => $this->provider->name(),
            'answer_translated_at' => now(),
        ]);
    }
}
