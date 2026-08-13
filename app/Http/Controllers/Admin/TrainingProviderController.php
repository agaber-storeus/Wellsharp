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
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TrainingProvider::class);
        $query = $this->filteredQuery($request);
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['provider_number', 'name', 'email', 'status', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $providers = $query->orderBy('training_providers.'.$sort, $direction)
            ->paginate(25, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $providers->getCollection()->map(fn (TrainingProvider $provider): array => $this->providerPayload($provider))->values(),
            'meta' => $this->paginationMeta($providers),
        ]);
    }

    public function index(): View
    {
        $this->authorize('viewAny', TrainingProvider::class);
        $providers = $this->filteredQuery(request())->latest('training_providers.created_at')->paginate(15)->withQueryString();
        $initialProviders = $providers->getCollection()->map(fn (TrainingProvider $provider): array => $this->providerPayload($provider))->values();

        return view('admin.providers.index', [
            'providers' => $providers,
            'initialProviders' => $initialProviders,
            'initialMeta' => $this->paginationMeta($providers),
            'search' => trim((string) request('search')),
            'status' => request('status'),
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
