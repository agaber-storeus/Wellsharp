<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProviderRequest;
use App\Http\Requests\Admin\UpdateProviderRequest;
use App\Http\Requests\Admin\UpdateProviderStatusRequest;
use App\Models\TrainingProvider;
use App\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(): View
    {
        $this->authorize('create', TrainingProvider::class);

        return view('admin.providers.create', ['provider' => new TrainingProvider]);
    }

    public function store(StoreProviderRequest $request, AuditRecorder $audit): RedirectResponse
    {
        $this->authorize('create', TrainingProvider::class);
        $provider = TrainingProvider::create([...$request->validated(), 'status' => ProviderStatus::Active]);
        $audit->record('training_provider.created', $provider, null, $provider->toArray());

        return redirect()->route('admin.providers.show', $provider)->with('status', 'Training provider created.');
    }

    public function show(TrainingProvider $provider): View
    {
        $this->authorize('view', $provider);

        return view('admin.providers.show', compact('provider'));
    }

    public function edit(TrainingProvider $provider): View
    {
        $this->authorize('update', $provider);

        return view('admin.providers.edit', compact('provider'));
    }

    public function update(UpdateProviderRequest $request, TrainingProvider $provider, AuditRecorder $audit): RedirectResponse
    {
        $this->authorize('update', $provider);
        $before = $provider->toArray();
        $provider->update($request->validated());
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

        return TrainingProvider::query()
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
}
