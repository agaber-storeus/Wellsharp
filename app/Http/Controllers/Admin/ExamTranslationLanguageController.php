<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateExamTranslationLanguagesRequest;
use App\Models\TranslationLanguage;
use App\Services\AuditRecorder;
use App\Services\Translation\QuestionTranslationService;
use App\Services\Translation\TranslationLanguageSyncService;
use App\Services\Translation\TranslationProviderException;
use App\Services\Translation\TranslationProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ExamTranslationLanguageController extends Controller
{
    public function index(TranslationProviderInterface $provider, QuestionTranslationService $questionTranslation): View
    {
        $languages = TranslationLanguage::query()->where('provider', $provider->name())->orderBy('name')->get();

        return view('admin.settings.exam-languages', [
            'providerName' => $provider->name(),
            'providerAvailable' => $this->providerAvailable($provider),
            'sourceLanguage' => $questionTranslation->sourceLanguage(),
            'languages' => $languages->map(fn (TranslationLanguage $language): array => $this->payload($language))->values(),
            'lastSyncedAt' => $languages->max('last_synced_at'),
        ]);
    }

    public function sync(TranslationLanguageSyncService $syncService, AuditRecorder $audit): JsonResponse
    {
        try {
            $languages = $syncService->sync();
        } catch (TranslationProviderException $e) {
            return response()->json(['message' => 'The translation provider could not be reached: '.$e->getMessage()], 422);
        }

        $audit->record('translation_language.synced', null, null, ['count' => $languages->count()]);

        return response()->json([
            'message' => 'Language catalog synchronized.',
            'rows' => $languages->map(fn (TranslationLanguage $language): array => $this->payload($language))->values(),
        ]);
    }

    public function update(UpdateExamTranslationLanguagesRequest $request, AuditRecorder $audit): JsonResponse
    {
        $enabledIds = collect($request->validated('enabled_ids'))->map(fn ($id): int => (int) $id)->all();

        $languages = DB::transaction(function () use ($enabledIds, $audit) {
            $all = TranslationLanguage::query()->get();
            foreach ($all as $language) {
                $shouldBeEnabled = in_array($language->getKey(), $enabledIds, true);
                if ($shouldBeEnabled === $language->is_enabled) {
                    continue;
                }
                $before = $language->toArray();
                $language->update(['is_enabled' => $shouldBeEnabled]);
                $audit->record('translation_language.updated', $language, $before, $language->fresh()->toArray());
            }

            return TranslationLanguage::query()->orderBy('name')->get();
        });

        return response()->json([
            'message' => 'Exam translation languages updated.',
            'rows' => $languages->map(fn (TranslationLanguage $language): array => $this->payload($language))->values(),
        ]);
    }

    private function providerAvailable(TranslationProviderInterface $provider): bool
    {
        try {
            return $provider->isAvailable();
        } catch (Throwable) {
            return false;
        }
    }

    private function payload(TranslationLanguage $language): array
    {
        return [
            'id' => $language->getKey(),
            'code' => $language->code,
            'name' => $language->name,
            'native_name' => $language->native_name,
            'direction' => $language->direction->value,
            'enabled' => $language->is_enabled,
            'last_synced_at' => $language->last_synced_at?->toIso8601String(),
        ];
    }
}
