<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCourseReferenceRequest;
use App\Models\CourseLevel;
use App\Models\Language;
use App\Models\Stack;
use App\Models\Supplement;
use App\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CourseConfigurationController extends Controller
{
    private const TYPES = ['levels', 'stacks', 'supplements', 'languages'];

    private function model(string $type): string
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return match ($type) {
            'levels' => CourseLevel::class,
            'stacks' => Stack::class,
            'supplements' => Supplement::class,
            'languages' => Language::class,
        };
    }

    public function index(): View
    {
        $items = [];
        foreach (self::TYPES as $type) {
            $model = $this->model($type);
            $items[$type] = $model::query()->orderBy('sort_order')->orderBy('name')->get();
        }

        return view('admin.configuration.index', compact('items'));
    }

    public function store(string $type, SaveCourseReferenceRequest $request, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        $model = $this->model($type);
        $data = $request->validated();
        if ($model::where('slug', Str::slug($data['name']))->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'That reference value already exists.', 'errors' => ['name' => ['That reference value already exists.']]], 422);
            }

            return back()->withErrors(['name' => 'That reference value already exists.'])->withInput();
        }
        $item = DB::transaction(function () use ($data, $model): object {
            $model::query()->increment('sort_order');

            return $model::create(['name' => $data['name'], 'slug' => Str::slug($data['name']), 'sort_order' => 0, 'is_active' => true]);
        });
        $audit->record("course_reference.{$type}.created", $item, null, $item->toArray());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Reference value created.', 'row' => $this->payload($type, $item)], 201);
        }

        return back()->with('status', 'Reference value created.');
    }

    public function update(string $type, int $item, SaveCourseReferenceRequest $request, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        $model = $this->model($type);
        $reference = $model::findOrFail($item);
        $data = $request->validated();
        if ($model::where('slug', Str::slug($data['name']))->where($reference->getKeyName(), '!=', $reference->getKey())->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'That reference value already exists.', 'errors' => ['name' => ['That reference value already exists.']]], 422);
            }

            return back()->withErrors(['name' => 'That reference value already exists.'])->withInput();
        }
        $before = $reference->toArray();
        $reference->update(['name' => $data['name'], 'slug' => Str::slug($data['name']), 'sort_order' => $data['sort_order'] ?? $reference->sort_order]);
        $audit->record("course_reference.{$type}.updated", $reference, $before, $reference->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Reference value updated.', 'row' => $this->payload($type, $reference->fresh())]);
        }

        return back()->with('status', 'Reference value updated.');
    }

    public function toggle(string $type, int $item, Request $request, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        $model = $this->model($type);
        $reference = $model::findOrFail($item);
        $before = $reference->toArray();
        $reference->update(['is_active' => ! $reference->is_active]);
        $audit->record("course_reference.{$type}.toggled", $reference, $before, $reference->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Reference value status updated.', 'row' => $this->payload($type, $reference->fresh())]);
        }

        return back()->with('status', 'Reference value status updated.');
    }

    public function reorder(string $type, Request $request, AuditRecorder $audit): JsonResponse
    {
        $model = $this->model($type);
        $validated = $request->validate(['order' => ['required', 'array', 'min:1'], 'order.*' => ['required', 'integer', 'distinct']]);
        $ids = collect($validated['order'])->map(fn ($id): int => (int) $id)->values();
        $references = $model::query()->whereIn('id', $ids->all())->get()->keyBy('id');

        if ($references->count() !== $ids->count()) {
            return response()->json(['message' => 'The configuration order contains an invalid value.'], 422);
        }

        DB::transaction(function () use ($audit, $references, $ids, $type): void {
            foreach ($ids as $position => $id) {
                $reference = $references->get($id);
                if ((int) $reference->sort_order === $position) {
                    continue;
                }
                $before = $reference->toArray();
                $reference->update(['sort_order' => $position]);
                $audit->record("course_reference.{$type}.reordered", $reference, $before, $reference->fresh()->toArray());
            }
        });

        return response()->json([
            'message' => 'Configuration order updated.',
            'rows' => $model::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn ($item): array => $this->payload($type, $item))->values(),
        ]);
    }

    private function payload(string $type, object $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'sort_order' => $item->sort_order,
            'active' => $item->is_active,
            'update_url' => route('admin.configuration.update', [$type, $item->id]),
            'toggle_url' => route('admin.configuration.toggle', [$type, $item->id]),
        ];
    }
}
