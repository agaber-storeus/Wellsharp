<?php

namespace App\Services\Translation;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionOptionTranslation;
use App\Models\QuestionTranslation;
use App\Models\TranslationLanguage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Owns the reusable Question/QuestionOption translation cache: fingerprint
 * comparison, cache lookup, stale detection, missing-translation batching
 * through the provider, persistence, and concurrency coordination. It does
 * not know about Exams, Attempts, students, or scoring - callers (Exam
 * Attempt start, Text Input scoring) hand it exactly the Questions they
 * need translated and get back translated text to snapshot themselves.
 */
class QuestionTranslationService
{
    public function __construct(private readonly TranslationProviderInterface $provider) {}

    public function sourceLanguage(): string
    {
        return (string) config('translation.source_language', 'en');
    }

    /**
     * Resolves translated presentation for exactly the given Questions (and
     * their MCQ options) - never more. Missing/stale Question text and
     * Option text are batched into a single provider call together (not one
     * request per Question and a separate one per Option); everything
     * already cached is served without any provider call.
     *
     * @param  Collection<int, Question>  $questions
     * @return array<int, array{question_text: string, options: array<string, string>, translated: bool}> keyed by question_id
     */
    public function resolveForQuestions(Collection $questions, TranslationLanguage $language): array
    {
        $questionIds = $questions->pluck('id')->unique()->values();
        if ($questionIds->isEmpty()) {
            return [];
        }

        // Re-queried fresh (not the caller's collection): callers may hand in a
        // plain Support\Collection (e.g. via ->pluck() on an assignment list)
        // rather than an Eloquent Collection, and may or may not have Options
        // eager-loaded already - this guarantees both regardless of caller state.
        $questions = Question::query()->whereIn('id', $questionIds)->with('options')->get();

        $options = $questions->flatMap(fn (Question $question): Collection => $question->options)->values();

        $questionSources = $questions->mapWithKeys(fn (Question $question): array => [$question->getKey() => [
            'text' => $question->question_text,
            'hash' => $question->question_text_hash,
        ]])->all();

        $optionSources = $options->mapWithKeys(fn (QuestionOption $option): array => [$option->getKey() => [
            'text' => $option->option_text,
            'hash' => $option->sourceHash(),
        ]])->all();

        [$questionCache, $optionCache] = $this->resolveBoth($language, $questionSources, $optionSources);

        return $this->assemble($questions, $questionCache, $optionCache);
    }

    /** @return array{0: array<int, array{text: string, ok: bool}>, 1: array<int, array{text: string, ok: bool}>} [questionId => resolution, optionId => resolution] */
    private function resolveBoth(TranslationLanguage $language, array $questionSources, array $optionSources): array
    {
        [$resolvedQuestions, $missingQuestionIds] = $this->splitCached(
            $this->cachedQuestionTranslations(array_keys($questionSources), $language), $questionSources,
        );
        [$resolvedOptions, $missingOptionIds] = $this->splitCached(
            $this->cachedOptionTranslations(array_keys($optionSources), $language), $optionSources,
        );

        if ($missingQuestionIds === [] && $missingOptionIds === []) {
            return [$resolvedQuestions, $resolvedOptions];
        }

        // A coarse per-language lock, not per-Question/Option: cache-fill misses
        // are expected to be rare after warmup (steady state is cache hits, which
        // never touch this lock), so serializing concurrent fills for one language
        // is a simple, correct trade-off rather than fine-grained per-item locking.
        // The unique DB constraint on each translation table is still the actual
        // guarantee against duplicate persistence.
        return Cache::lock("translation:cache-fill:{$language->getKey()}", 30)->block(15, function () use (
            $language, $questionSources, $optionSources, $missingQuestionIds, $missingOptionIds, $resolvedQuestions, $resolvedOptions,
        ): array {
            // Re-query fresh (not the pre-lock snapshot): another request may have
            // just inserted or updated these rows while we waited for the lock.
            [$resolvedQuestions, $stillMissingQuestionIds] = $this->splitCached(
                $this->cachedQuestionTranslations($missingQuestionIds, $language), $questionSources, $resolvedQuestions,
            );
            [$resolvedOptions, $stillMissingOptionIds] = $this->splitCached(
                $this->cachedOptionTranslations($missingOptionIds, $language), $optionSources, $resolvedOptions,
            );

            if ($stillMissingQuestionIds === [] && $stillMissingOptionIds === []) {
                return [$resolvedQuestions, $resolvedOptions];
            }

            // One combined provider call for every still-missing Question and Option
            // text together - not one request per string, and not one request per
            // Question separate from its Options.
            $texts = [
                ...array_map(fn (int $id): string => $questionSources[$id]['text'], $stillMissingQuestionIds),
                ...array_map(fn (int $id): string => $optionSources[$id]['text'], $stillMissingOptionIds),
            ];
            $results = $this->provider->translate($texts, $this->sourceLanguage(), $language->code);

            $index = 0;
            foreach ($stillMissingQuestionIds as $id) {
                $resolvedQuestions[$id] = $this->applyResult(false, $id, $language, $questionSources[$id], $results[$index] ?? null);
                $index++;
            }
            foreach ($stillMissingOptionIds as $id) {
                $resolvedOptions[$id] = $this->applyResult(true, $id, $language, $optionSources[$id], $results[$index] ?? null);
                $index++;
            }

            return [$resolvedQuestions, $resolvedOptions];
        });
    }

    /**
     * @param  array<int, array{text: string, ok: bool}>  $resolved  Already-resolved ids to keep untouched (from the pre-lock pass).
     * @param  array<int, array{text: string, hash: string}>  $sources
     * @param  array<int, QuestionTranslation|QuestionOptionTranslation>  $cached
     * @return array{0: array<int, array{text: string, ok: bool}>, 1: array<int, int>} [id => resolution, still-missing ids]
     */
    private function splitCached(array $cached, array $sources, array $resolved = []): array
    {
        $missing = [];
        foreach ($sources as $id => $source) {
            if (array_key_exists($id, $resolved)) {
                continue;
            }
            $row = $cached[$id] ?? null;
            if ($row && $row->source_hash === $source['hash']) {
                $resolved[$id] = ['text' => $row->translated_text, 'ok' => true];
            } else {
                $missing[] = $id;
            }
        }

        return [$resolved, $missing];
    }

    /** @return array{text: string, ok: bool} */
    private function applyResult(bool $isOption, int $id, TranslationLanguage $language, array $source, ?TranslationResult $result): array
    {
        $result ??= TranslationResult::failure('Provider returned no result for this item.');

        if (! $result->success) {
            // Leave untranslated for this pass; original text is used as the
            // fallback by the caller and the cache miss is simply retried on the
            // next resolution attempt - no bad value is ever persisted. The
            // caller is told this item is not actually translated (ok: false)
            // so it can avoid presenting source-language text under a
            // target-language reading direction.
            return ['text' => $source['text'], 'ok' => false];
        }

        $this->persist($isOption, $id, $language, $source['hash'], $result->text);

        return ['text' => $result->text, 'ok' => true];
    }

    /** @return array<int, array{question_text: string, options: array<string, string>, translated: bool}> */
    private function assemble(Collection $questions, array $questionCache, array $optionCache): array
    {
        return $questions->mapWithKeys(function (Question $question) use ($questionCache, $optionCache): array {
            $questionResolution = $questionCache[$question->getKey()] ?? ['text' => $question->question_text, 'ok' => true];
            $translated = $questionResolution['ok'];

            $questionOptions = $question->options->mapWithKeys(function (QuestionOption $option) use ($optionCache, &$translated): array {
                $optionResolution = $optionCache[$option->getKey()] ?? ['text' => $option->option_text, 'ok' => true];
                $translated = $translated && $optionResolution['ok'];

                return [$option->public_id => $optionResolution['text']];
            })->all();

            return [$question->getKey() => [
                'question_text' => $questionResolution['text'],
                'options' => $questionOptions,
                'translated' => $translated,
            ]];
        })->all();
    }

    /** @return array<int, QuestionTranslation> keyed by question_id */
    private function cachedQuestionTranslations(array $ids, TranslationLanguage $language): array
    {
        return $this->cachedTranslations(QuestionTranslation::class, 'question_id', $ids, $language);
    }

    /** @return array<int, QuestionOptionTranslation> keyed by question_option_id */
    private function cachedOptionTranslations(array $ids, TranslationLanguage $language): array
    {
        return $this->cachedTranslations(QuestionOptionTranslation::class, 'question_option_id', $ids, $language);
    }

    /**
     * @param  class-string<QuestionTranslation|QuestionOptionTranslation>  $modelClass
     * @param  array<int, int>  $ids
     * @return array<int, QuestionTranslation|QuestionOptionTranslation> keyed by $foreignKey
     */
    private function cachedTranslations(string $modelClass, string $foreignKey, array $ids, TranslationLanguage $language): array
    {
        if ($ids === []) {
            return [];
        }

        return $modelClass::query()
            ->whereIn($foreignKey, $ids)
            ->where('translation_language_id', $language->getKey())
            ->get()
            ->keyBy($foreignKey)
            ->all();
    }

    private function persist(bool $isOption, int $id, TranslationLanguage $language, string $sourceHash, string $translatedText): void
    {
        $attributes = ['source_hash' => $sourceHash, 'translated_text' => $translatedText, 'provider' => $this->provider->name(), 'translated_at' => now()];

        if ($isOption) {
            QuestionOptionTranslation::query()->updateOrCreate(
                ['question_option_id' => $id, 'translation_language_id' => $language->getKey()],
                $attributes,
            );

            return;
        }

        QuestionTranslation::query()->updateOrCreate(
            ['question_id' => $id, 'translation_language_id' => $language->getKey()],
            $attributes,
        );
    }
}
