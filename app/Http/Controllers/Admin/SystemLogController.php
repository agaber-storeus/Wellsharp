<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemLogIndexRequest;
use App\Services\SystemLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SystemLogController extends Controller
{
    public function index(SystemLogIndexRequest $request, SystemLogService $logs): View
    {
        $filters = $request->validated();
        $logsPage = $logs->paginate($filters, (int) $request->integer('page', 1));

        return view('admin.system-logs.index', [
            'filters' => $filters,
            'initialLogs' => $logsPage->getCollection()->map(fn (array $entry): array => $this->logPayload($entry))->values(),
            'initialMeta' => $this->paginationMeta($logsPage),
            'categories' => SystemLogService::categories(),
            'actions' => $logs->actionOptions(),
            'actors' => $logs->actorOptions(),
            'roles' => SystemLogService::roles(),
            'subjectTypes' => $logs->subjectTypeOptions(),
        ]);
    }

    public function data(SystemLogIndexRequest $request, SystemLogService $logs): JsonResponse
    {
        $filters = $request->validated();
        $logsPage = $logs->paginate($filters, (int) $request->integer('page', 1));

        return response()->json([
            'data' => $logsPage->getCollection()->map(fn (array $entry): array => $this->logPayload($entry))->values(),
            'meta' => $this->paginationMeta($logsPage),
        ]);
    }

    public function show(Request $request, string $source, string $publicId, SystemLogService $logs): View
    {
        abort_unless($request->user()?->isAdmin() === true, 403);

        return view('admin.system-logs.show', [
            'entry' => $logs->find($source, $publicId),
        ]);
    }

    /** @return array<string, mixed> */
    private function logPayload(array $entry): array
    {
        return [
            'id' => $entry['id'],
            'occurred_at' => $entry['occurred_at']->format('M j, Y g:i A'),
            'category_label' => $entry['category_label'],
            'label' => $entry['label'],
            'actor' => $entry['actor'],
            'actor_role' => $entry['actor_role'],
            'subject' => $entry['subject'],
            'result' => $entry['result'],
            'severity' => $entry['severity'],
            'reason' => $entry['reason'],
            'correlation_id' => $entry['correlation_id'],
            'detail_url' => $entry['detail_url'],
        ];
    }

    /** @return array<string, int|null> */
    private function paginationMeta(LengthAwarePaginator $logs): array
    {
        return [
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'total' => $logs->total(),
            'from' => $logs->firstItem(),
            'to' => $logs->lastItem(),
        ];
    }
}
