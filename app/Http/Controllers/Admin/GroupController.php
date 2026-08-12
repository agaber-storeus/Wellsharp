<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Groups\AddStudentsToGroupAction;
use App\Actions\Groups\RemoveStudentFromGroupAction;
use App\Actions\Groups\SaveGroupAction;
use App\Enums\GroupStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddGroupMembersRequest;
use App\Http\Requests\Admin\StoreGroupRequest;
use App\Http\Requests\Admin\UpdateGroupRequest;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Group::class);
        $term = trim((string) $request->input('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $groups = Group::query()
            ->where('status', GroupStatus::Active)
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'code']);

        return response()->json($groups->map(fn (Group $group): array => [
            'id' => $group->getKey(),
            'name' => $group->name,
            'code' => $group->code,
        ])->values());
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Group::class);
        $groups = $this->groupQuery($request)->paginate($this->perPage($request))->withQueryString();
        $initialGroups = $groups->getCollection()->map(fn (Group $group): array => $this->groupPayload($group))->values();
        $initialMeta = $this->paginationMeta($groups);

        return view('admin.groups.index', [
            'groups' => $groups,
            'initialGroups' => $initialGroups,
            'initialMeta' => $initialMeta,
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', ''),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Group::class);
        $groups = $this->groupQuery($request)->paginate($this->perPage($request));

        return response()->json([
            'data' => $groups->getCollection()->map(fn (Group $group): array => $this->groupPayload($group))->values(),
            'meta' => $this->paginationMeta($groups),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Group::class);

        return view('admin.groups.create', ['group' => new Group]);
    }

    public function store(StoreGroupRequest $request, SaveGroupAction $action): RedirectResponse
    {
        $this->authorize('create', Group::class);
        $group = $action->execute(null, $request->validated());

        return redirect()->route('admin.groups.show', $group)->with('status', 'Group created.');
    }

    public function show(Group $group): View
    {
        $this->authorize('view', $group);
        $students = $group->students()->with('profile')->orderBy('wellsharp_id')->get();
        $studentSearch = trim((string) request('student_search'));
        $availableStudents = User::query()->whereHas('currentRole', fn ($role) => $role->where('key', Role::STUDENT))->whereDoesntHave('groups', fn ($query) => $query->whereKey($group->getKey()))->when($studentSearch, fn ($query) => $query->where(function ($query) use ($studentSearch): void {
            $query->where('wellsharp_id', 'like', "%{$studentSearch}%")
                ->orWhereHas('profile', fn ($profile) => $profile->where('first_name', 'like', "%{$studentSearch}%")->orWhere('last_name', 'like', "%{$studentSearch}%"));
        }))->with('profile')->orderBy('wellsharp_id')->get();
        $group->loadCount('examSchedules');

        return view('admin.groups.show', compact('group', 'students', 'availableStudents', 'studentSearch'));
    }

    public function edit(Group $group): View
    {
        $this->authorize('update', $group);

        return view('admin.groups.edit', compact('group'));
    }

    public function update(UpdateGroupRequest $request, Group $group, SaveGroupAction $action): RedirectResponse
    {
        $this->authorize('update', $group);
        $action->execute($group, $request->validated());

        return redirect()->route('admin.groups.show', $group)->with('status', 'Group updated.');
    }

    public function archive(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);
        $group->update(['status' => GroupStatus::Archived, 'updated_by_user_id' => auth()->id()]);
        app(AuditRecorder::class)->record('group.updated', $group, ['status' => GroupStatus::Active->value], ['status' => GroupStatus::Archived->value]);

        return back()->with('status', 'Group archived.');
    }

    public function addMembers(AddGroupMembersRequest $request, Group $group, AddStudentsToGroupAction $action): RedirectResponse
    {
        $this->authorize('update', $group);
        $action->execute($group, $request->validated('student_ids'));

        return back()->with('status', 'Students added to group.');
    }

    public function removeMember(Group $group, User $student, RemoveStudentFromGroupAction $action): RedirectResponse
    {
        $this->authorize('update', $group);
        $action->execute($group, $student);

        return back()->with('status', 'Student removed from group.');
    }

    private function groupQuery(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sorts = [
            'name' => 'student_groups.name',
            'code' => 'student_groups.code',
            'students_count' => 'students_count',
            'exam_schedules_count' => 'exam_schedules_count',
            'status' => 'student_groups.status',
            'created_at' => 'student_groups.created_at',
        ];

        return Group::query()
            ->withCount(['students', 'examSchedules'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderBy($sorts[$sort] ?? $sorts['created_at'], $direction)
            ->orderBy('student_groups.id');
    }

    private function groupPayload(Group $group): array
    {
        return [
            'id' => $group->public_id,
            'name' => $group->name,
            'code' => $group->code,
            'students_count' => (int) $group->students_count,
            'exam_schedules_count' => (int) $group->exam_schedules_count,
            'status' => $group->status->value,
            'status_label' => $group->status->label(),
            'view_url' => route('admin.groups.show', $group),
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

    private function perPage(Request $request): int
    {
        return in_array((int) $request->query('per_page', 15), [15, 30, 50, 100], true) ? (int) $request->query('per_page', 15) : 15;
    }
}
