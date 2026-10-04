<?php

namespace App\Http\Controllers\Admin;

use App\Actions\TrainingProviders\AutosaveProviderLocationsAction;
use App\Actions\TrainingProviders\SyncProviderLocationsAction;
use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AutosaveProviderLocationsRequest;
use App\Http\Requests\Admin\StoreProviderRequest;
use App\Http\Requests\Admin\UpdateProviderRequest;
use App\Http\Requests\Admin\UpdateProviderStatusRequest;
use App\Models\TrainingProvider;
use App\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TrainingProviderController extends Controller
{
    private const PER_PAGE = 25;

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TrainingProvider::class);
        $query = $this->filteredQuery($request);
        [$sort, $direction] = $this->sortValues($request);
        $providers = $query->orderBy('training_providers.'.$sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $this->safePage($query, $request));

        return response()->json([
            'data' => $providers->getCollection()->map(fn (TrainingProvider $provider): array => $this->providerPayload($provider))->values(),
            'meta' => $this->paginationMeta($providers),
        ]);
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', TrainingProvider::class);
        $query = $this->filteredQuery($request);
        [$sort, $direction] = $this->sortValues($request);
        $providers = $query->orderBy('training_providers.'.$sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $this->safePage($query, $request))->withQueryString();
        $initialProviders = $providers->getCollection()->map(fn (TrainingProvider $provider): array => $this->providerPayload($provider))->values();

        return view('admin.providers.index', [
            'providers' => $providers,
            'initialProviders' => $initialProviders,
            'initialMeta' => $this->paginationMeta($providers),
            'search' => trim((string) request('search')),
            'status' => request('status'),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', TrainingProvider::class);
        $this->cleanupLocationDrafts($request);
        $draftToken = (string) $request->session()->get('active_provider_location_draft');
        $draft = $draftToken !== '' ? $this->ownedLocationDraft($request, $draftToken) : null;

        if (! $draft) {
            $draftToken = (string) Str::uuid();
            $draft = ['user_id' => $request->user()->getKey(), 'updated_at' => now()->timestamp, 'locations' => []];
            $request->session()->put($this->locationDraftKey($draftToken), $draft);
            $request->session()->put('active_provider_location_draft', $draftToken);
        }

        return view('admin.providers.create', [
            'provider' => new TrainingProvider,
            'autosaveLocations' => $draft['locations'],
            'autosaveUrl' => route('admin.providers.location-drafts.update', $draftToken),
            'draftToken' => $draftToken,
        ]);
    }

    public function store(StoreProviderRequest $request, AuditRecorder $audit, SyncProviderLocationsAction $syncLocations): RedirectResponse
    {
        $this->authorize('create', TrainingProvider::class);
        $data = $request->validated();
        $draftToken = (string) ($data['draft_token'] ?? '');
        $draft = $draftToken !== '' ? $this->ownedLocationDraft($request, $draftToken) : null;
        abort_if($draftToken !== '' && ! $draft, 403, 'This provider location draft is unavailable.');
        $locations = array_key_exists('locations', $data) ? $this->locationData($data) : ($draft['locations'] ?? $this->locationData($data));

        $provider = DB::transaction(function () use ($data, $locations, $syncLocations): TrainingProvider {
            $provider = TrainingProvider::create([...$data, 'status' => ProviderStatus::Active]);
            $syncLocations->execute($provider, $locations);

            return $provider;
        });
        if ($draft) {
            $request->session()->forget($this->locationDraftKey($draftToken));
            if ($request->session()->get('active_provider_location_draft') === $draftToken) {
                $request->session()->forget('active_provider_location_draft');
            }
        }
        $audit->record('training_provider.created', $provider, null, $provider->toArray());

        return redirect()->route('admin.providers.show', $provider)->with('status', 'Training provider created.');
    }

    public function show(TrainingProvider $provider): View
    {
        $this->authorize('view', $provider);

        return view('admin.providers.show', ['provider' => $provider->load('locations')]);
    }

    public function edit(TrainingProvider $provider): View
    {
        $this->authorize('update', $provider);
        $editDraft = request()->session()->get($this->editLocationDraftKey($provider));
        if (is_array($editDraft) && (int) ($editDraft['updated_at'] ?? 0) < $this->locationDraftCutoff()) {
            request()->session()->forget($this->editLocationDraftKey($provider));
            $editDraft = null;
        }

        return view('admin.providers.edit', [
            'provider' => $provider->load('locations'),
            'autosaveLocations' => is_array($editDraft) ? ($editDraft['locations'] ?? null) : null,
            'autosaveUrl' => route('admin.providers.locations.autosave', $provider),
            'draftToken' => null,
        ]);
    }

    public function autosaveDraftLocations(AutosaveProviderLocationsRequest $request, string $draftToken): JsonResponse
    {
        $this->authorize('create', TrainingProvider::class);
        $draft = $this->ownedLocationDraft($request, $draftToken);
        abort_unless($draft, 403, 'This provider location draft is unavailable.');

        $locations = collect($request->validated('locations'))->map(fn (array $location): array => [
            'client_key' => $location['client_key'],
            'location' => trim((string) ($location['location'] ?? '')),
            'latitude' => filled($location['latitude'] ?? null) ? (float) $location['latitude'] : null,
            'longitude' => filled($location['longitude'] ?? null) ? (float) $location['longitude'] : null,
        ])->values()->all();

        $request->session()->put($this->locationDraftKey($draftToken), [
            'user_id' => $request->user()->getKey(),
            'updated_at' => now()->timestamp,
            'locations' => $locations,
        ]);

        return response()->json(['locations' => $locations]);
    }

    public function autosaveLocations(
        AutosaveProviderLocationsRequest $request,
        TrainingProvider $provider,
        AutosaveProviderLocationsAction $autosaveLocations,
    ): JsonResponse {
        $this->authorize('update', $provider);
        $locations = $autosaveLocations->execute($provider, $request->validated('locations'));
        $request->session()->put($this->editLocationDraftKey($provider), [
            'updated_at' => now()->timestamp,
            'locations' => $locations,
        ]);

        return response()->json(['locations' => $locations]);
    }

    public function update(UpdateProviderRequest $request, TrainingProvider $provider, AuditRecorder $audit, SyncProviderLocationsAction $syncLocations): RedirectResponse
    {
        $this->authorize('update', $provider);
        $before = $provider->toArray();
        $data = $request->validated();
        DB::transaction(function () use ($provider, $data, $syncLocations): void {
            $provider->update($data);
            if (array_key_exists('locations', $data) || array_key_exists('address', $data)) {
                $syncLocations->execute($provider, $this->locationData($data));
            }
        });
        $request->session()->forget($this->editLocationDraftKey($provider));
        $audit->record('training_provider.updated', $provider, $before, $provider->fresh()->toArray());

        return redirect()->route('admin.providers.show', $provider)->with('status', 'Training provider updated.');
    }

    public function updateStatus(UpdateProviderStatusRequest $request, TrainingProvider $provider, AuditRecorder $audit): JsonResponse
    {
        $this->authorize('update', $provider);

        if ($provider->archived_at !== null || $provider->status === ProviderStatus::Archived) {
            return response()->json(['message' => 'Archived providers cannot be reactivated or deactivated.'], 422);
        }

        $status = ProviderStatus::from($request->validated('status'));
        $before = $provider->toArray();

        if ($provider->status !== $status) {
            $provider->forceFill(['status' => $status])->save();
            $audit->record('training_provider.status_updated', $provider, $before, $provider->fresh()->toArray());
        }

        return response()->json([
            'message' => 'Training provider status updated.',
            'status' => $provider->status->value,
            'status_label' => $provider->status->label(),
        ]);
    }

    public function archive(Request $request, TrainingProvider $provider, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $provider);
        $before = $provider->toArray();
        $provider->forceFill(['status' => ProviderStatus::Archived, 'archived_at' => now()])->save();
        $audit->record('training_provider.archived', $provider, $before, $provider->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Training provider archived.',
                'status' => ProviderStatus::Archived->value,
                'status_label' => ProviderStatus::Archived->label(),
            ]);
        }

        return back()->with('status', 'Training provider archived.');
    }

    public function unarchive(Request $request, TrainingProvider $provider, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $provider);
        abort_unless($provider->status === ProviderStatus::Archived || $provider->archived_at !== null, 422, 'This provider is not archived.');
        $before = $provider->toArray();
        $provider->forceFill(['status' => ProviderStatus::Active, 'archived_at' => null])->save();
        $audit->record('training_provider.unarchived', $provider, $before, $provider->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Training provider unarchived and restored to Active.', 'status' => ProviderStatus::Active->value, 'status_label' => ProviderStatus::Active->label()]);
        }

        return back()->with('status', 'Training provider unarchived and restored to Active.');
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $status = $request->input('status');

        return TrainingProvider::query()->with(['locations' => fn ($query) => $query->where('is_active', true)->orderBy('location')])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('provider_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($status, fn ($query) => $query->where('status', $status));
    }

    private function safePage($query, Request $request): int
    {
        $requested = max(1, (int) $request->input('page', 1));
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));

        return min($requested, $lastPage);
    }

    /** @return array{0: string, 1: string} */
    private function sortValues(Request $request): array
    {
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowed = ['provider_number', 'name', 'email', 'status', 'created_at'];

        return [in_array($sort, $allowed, true) ? $sort : 'created_at', $direction];
    }

    private function providerPayload(TrainingProvider $provider): array
    {
        return [
            'id' => $provider->getKey(),
            'provider_number' => $provider->provider_number,
            'name' => $provider->name,
            'email' => $provider->email ?: 'No email',
            'locations' => $provider->locations->pluck('location')->implode(', ') ?: 'No locations',
            'status' => $provider->status->value,
            'status_label' => $provider->status->label(),
            'saved_status' => $provider->status->value,
            'status_url' => route('admin.providers.status', $provider),
            'archive_url' => route('admin.providers.archive', $provider),
            'can_unarchive' => $provider->status === ProviderStatus::Archived || $provider->archived_at !== null,
            'unarchive_url' => route('admin.providers.unarchive', $provider),
            'view_url' => route('admin.providers.show', $provider),
        ];
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function locationData(array $data): array
    {
        if (array_key_exists('locations', $data)) {
            $locations = $data['locations'] ?? [];
            if (isset($locations[0])) {
                $locations[0]['latitude'] = filled($locations[0]['latitude'] ?? null) ? $locations[0]['latitude'] : ($data['latitude'] ?? null);
                $locations[0]['longitude'] = filled($locations[0]['longitude'] ?? null) ? $locations[0]['longitude'] : ($data['longitude'] ?? null);
            }

            return $locations;
        }

        return filled($data['address'] ?? null) ? [[
            'location' => $data['address'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]] : [];
    }

    private function locationDraftKey(string $draftToken): string
    {
        return 'provider_location_drafts.'.$draftToken;
    }

    private function ownedLocationDraft(Request $request, string $draftToken): ?array
    {
        if (! Str::isUuid($draftToken)) {
            return null;
        }

        $draft = $request->session()->get($this->locationDraftKey($draftToken));

        if (is_array($draft) && (int) ($draft['updated_at'] ?? 0) < $this->locationDraftCutoff()) {
            $request->session()->forget($this->locationDraftKey($draftToken));
            return null;
        }

        return is_array($draft) && (int) ($draft['user_id'] ?? 0) === (int) $request->user()->getKey()
            ? $draft
            : null;
    }

    private function cleanupLocationDrafts(Request $request): void
    {
        $cutoff = $this->locationDraftCutoff();
        $drafts = $request->session()->get('provider_location_drafts', []);

        foreach ($drafts as $token => $draft) {
            if (! is_array($draft) || (int) ($draft['updated_at'] ?? 0) < $cutoff) {
                unset($drafts[$token]);
            }
        }

        $request->session()->put('provider_location_drafts', $drafts);
    }

    private function editLocationDraftKey(TrainingProvider $provider): string
    {
        return 'provider_location_edit_drafts.'.$provider->getKey();
    }

    private function locationDraftCutoff(): int
    {
        return now()->subMinutes((int) config('session.lifetime'))->timestamp;
    }
}
