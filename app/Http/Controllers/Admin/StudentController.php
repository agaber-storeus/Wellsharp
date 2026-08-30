<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateUserAction;
use App\Enums\GroupStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $students = $this->studentQuery($request)->paginate($this->perPage($request))->withQueryString();
        $initialStudents = $students->getCollection()->map(fn (User $student): array => $this->studentPayload($student))->values();
        $initialMeta = $this->paginationMeta($students);

        return view('admin.students.index', [
            'students' => $students,
            'initialStudents' => $initialStudents,
            'initialMeta' => $initialMeta,
            'search' => trim((string) $request->query('search')),
            'gender' => (string) $request->query('gender', ''),
            'groupId' => (string) $request->query('group_id', ''),
            'status' => (string) $request->query('status', ''),
            'groups' => Group::query()->where('status', GroupStatus::Active)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $students = $this->studentQuery($request)->paginate($this->perPage($request));

        return response()->json([
            'data' => $students->getCollection()->map(fn (User $student): array => $this->studentPayload($student))->values(),
            'meta' => $this->paginationMeta($students),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.students.create', ['user' => new User, 'selectedGroupOptions' => collect(), 'studentOnly' => true, 'studentRoleId' => Role::where('key', Role::STUDENT)->value('id')]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validated();
        $data['role_id'] = Role::where('key', Role::STUDENT)->value('id');
        $result = $action->execute($data);

        return redirect()->route('admin.users.show', $result->user)
            ->with('status', 'Student created successfully.')
            ->with('generated_password', $result->generatedPassword);
    }

    private function studentQuery(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sorts = [
            'wellsharp_id' => 'users.wellsharp_id',
            'name' => UserProfile::select('first_name')->whereColumn('user_profiles.user_id', 'users.id'),
            'age' => UserProfile::select('age')->whereColumn('user_profiles.user_id', 'users.id'),
            'gender' => UserProfile::select('gender')->whereColumn('user_profiles.user_id', 'users.id'),
            'groups_count' => 'groups_count',
            'status' => 'users.status',
            'created_at' => 'users.created_at',
        ];

        $query = User::query()
            ->whereHas('currentRole', fn ($role) => $role->where('key', Role::STUDENT))
            ->with('profile')
            ->withCount('groups')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('wellsharp_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn ($profile) => $profile->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('gender'), fn ($query) => $query->whereHas('profile', fn ($profile) => $profile->where('gender', $request->query('gender'))))
            ->when($request->filled('group_id'), fn ($query) => $query->whereHas('groups', fn ($group) => $group->whereKey($request->query('group_id'))))
            ->when(in_array($request->query('status'), ['active', 'disabled', 'archived'], true), fn ($query) => $query->where('users.status', $request->query('status')));

        $sortColumn = $sorts[$sort] ?? $sorts['created_at'];

        return $query->orderBy($sortColumn, $direction)->orderBy('users.id');
    }

    private function studentPayload(User $student): array
    {
        return [
            'id' => $student->public_id,
            'wellsharp_id' => $student->wellsharp_id,
            'display_name' => $student->display_name,
            'email' => $student->email,
            'age' => $student->profile?->age,
            'gender' => $student->profile?->gender,
            'groups_count' => (int) $student->groups_count,
            'status' => $student->status->value,
            'status_label' => $student->status->label(),
            'can_toggle_status' => in_array($student->status, [UserStatus::Active, UserStatus::Disabled], true) && ! $student->is(auth()->user()),
            'status_url' => route('admin.users.status', $student),
            'can_archive' => in_array($student->status, [UserStatus::Active, UserStatus::Disabled], true) && ! $student->is(auth()->user()),
            'archive_url' => route('admin.users.archive', $student),
            'can_unarchive' => $student->status === UserStatus::Archived && ! $student->is(auth()->user()),
            'unarchive_url' => route('admin.users.unarchive', $student),
            'view_url' => route('admin.users.show', $student),
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
