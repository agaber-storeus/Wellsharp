<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ArchiveUserAction;
use App\Actions\Users\ChangeUserRoleAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DisableUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Actions\Users\UpdateUserStatusAction;
use App\Actions\Users\UnarchiveUserAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeUserRoleRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $query = $this->filteredQuery($request);
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['name', 'wellsharp_id', 'role', 'status', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        if ($sort === 'name') {
            $query->leftJoin('user_profiles as user_sort_profile', 'user_sort_profile.user_id', '=', 'users.id')
                ->addSelect('users.*')
                ->orderBy('user_sort_profile.first_name', $direction)
                ->orderBy('user_sort_profile.last_name', $direction);
        } elseif ($sort === 'role') {
            $query->leftJoin('roles as user_role_sort', 'user_role_sort.id', '=', 'users.current_role_id')
                ->addSelect('users.*')
                ->orderBy('user_role_sort.name', $direction);
        } else {
            $query->orderBy('users.'.$sort, $direction);
        }

        $users = $query->paginate(25, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $users->getCollection()->map(fn (User $user): array => $this->userPayload($user))->values(),
            'meta' => $this->paginationMeta($users),
        ]);
    }

    public function index(): View
    {
        $this->authorize('viewAny', User::class);
        $users = $this->filteredQuery(request())->latest('users.created_at')->paginate(15)->withQueryString();
        $initialUsers = $users->getCollection()->map(fn (User $user): array => $this->userPayload($user))->values();
        $initialMeta = $this->paginationMeta($users);

        return view('admin.users.index', [
            'users' => $users,
            'initialUsers' => $initialUsers,
            'initialMeta' => $initialMeta,
            'search' => trim((string) request('search')),
            'roleId' => request('role_id'),
            'status' => request('status'),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'user' => new User,
            'selectedGroupOptions' => collect(),
            'roles' => Role::orderBy('name')->get(),
            'studentRoleId' => Role::where('key', Role::STUDENT)->value('id'),
            'proctorRoleId' => Role::where('key', Role::PROCTOR)->value('id'),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $this->authorize('create', User::class);
        $result = $action->execute($request->validated());

        return redirect()->route('admin.users.show', $result->user)
            ->with('status', 'User created successfully.')
            ->with('generated_password', $result->generatedPassword);
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('admin.users.show', ['user' => $user->load('profile', 'currentRole', 'groups', 'examControlCredential')]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('profile', 'currentRole', 'groups', 'examControlCredential');

        return view('admin.users.edit', [
            'user' => $user,
            'selectedGroupOptions' => $user->groups,
            'roles' => Role::orderBy('name')->get(),
            'studentRoleId' => Role::where('key', Role::STUDENT)->value('id'),
            'proctorRoleId' => Role::where('key', Role::PROCTOR)->value('id'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        $this->authorize('update', $user);
        $action->execute($user, $request->validated());

        return redirect()->route('admin.users.show', $user)->with('status', 'User updated successfully.');
    }

    public function changeRole(ChangeUserRoleRequest $request, User $user, ChangeUserRoleAction $action): RedirectResponse
    {
        $this->authorize('update', $user);
        $action->execute($user, Role::findOrFail($request->integer('role_id')));

        return back()->with('status', 'Role changed. Existing sessions for this user have been revoked.');
    }

    public function revealPassword(User $user, AuditRecorder $audit): JsonResponse
    {
        $this->authorize('viewPassword', $user);
        $password = $user->revealPassword();
        abort_if($password === null, 404, 'No recoverable password is stored for this account.');

        $audit->record($user->currentRole?->key === Role::STUDENT ? 'student.password_viewed' : 'user.password_viewed', $user, null, ['viewed_by_role' => auth()->user()->currentRole?->key]);

        return response()->json(['password' => $password])->header('Cache-Control', 'no-store');
    }

    public function disable(Request $request, User $user, DisableUserAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $user);
        abort_if($user->is(auth()->user()), 422, 'You cannot disable your own account.');
        $action->execute($user);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User disabled and existing sessions revoked.',
                'status' => 'disabled',
                'status_label' => 'Disabled',
            ]);
        }

        return back()->with('status', 'User disabled and existing sessions revoked.');
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user, UpdateUserStatusAction $action): JsonResponse
    {
        $this->authorize('update', $user);
        abort_if($user->is(auth()->user()), 422, 'You cannot change your own account status.');
        abort_if($user->status === UserStatus::Archived, 422, 'Archived users cannot be reactivated or deactivated.');

        $status = UserStatus::from($request->validated('status'));
        $updated = $action->execute($user, $status);

        return response()->json([
            'message' => 'User status updated.',
            'status' => $updated->status->value,
            'status_label' => $updated->status->label(),
        ]);
    }

    public function archive(Request $request, User $user, ArchiveUserAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $user);
        abort_if($user->is(auth()->user()), 422, 'You cannot archive your own account.');
        $action->execute($user);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User archived and existing sessions revoked.',
                'status' => 'archived',
                'status_label' => 'Archived',
            ]);
        }

        return back()->with('status', 'User archived and existing sessions revoked.');
    }

    public function unarchive(Request $request, User $user, UnarchiveUserAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $user);
        abort_if($user->is(auth()->user()), 422, 'You cannot unarchive your own account.');
        abort_unless($user->status === UserStatus::Archived, 422, 'This user is not archived.');
        $action->execute($user);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User unarchived and restored to Active.',
                'status' => UserStatus::Active->value,
                'status_label' => UserStatus::Active->label(),
            ]);
        }

        return back()->with('status', 'User unarchived and restored to Active.');
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $roleId = $request->input('role_id');
        $status = $request->input('status');

        return User::query()->with(['profile', 'currentRole'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('users.wellsharp_id', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhereHas('profile', fn ($profile) => $profile->where(function ($profile) use ($search): void {
                        $profile->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    }));
            }))
            ->when($roleId, fn ($query) => $query->where('users.current_role_id', $roleId))
            ->when($status, fn ($query) => $query->where('users.status', $status));
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'display_name' => $user->display_name,
            'email' => $user->email ?: 'No email',
            'phone' => $user->profile?->phone ?: 'No phone',
            'birthday' => $user->profile?->birthday?->format('Y-m-d') ?: 'Not set',
            'location' => collect([$user->profile?->city, $user->profile?->country])->filter()->implode(', ') ?: 'Not set',
            'wellsharp_id' => $user->wellsharp_id,
            'username' => $user->username,
            'proctor_id' => $user->examControlCredential?->control_id,
            'role' => $user->currentRole?->name ?: 'Unassigned',
            'status' => $user->status->value,
            'status_label' => $user->status->label(),
            'can_disable' => $user->isActive() && ! $user->is(auth()->user()),
            'disable_url' => route('admin.users.disable', $user),
            'can_toggle_status' => in_array($user->status, [UserStatus::Active, UserStatus::Disabled], true) && ! $user->is(auth()->user()),
            'status_url' => route('admin.users.status', $user),
            'can_archive' => in_array($user->status, [UserStatus::Active, UserStatus::Disabled], true) && ! $user->is(auth()->user()),
            'archive_url' => route('admin.users.archive', $user),
            'can_unarchive' => $user->status === UserStatus::Archived && ! $user->is(auth()->user()),
            'unarchive_url' => route('admin.users.unarchive', $user),
            'view_url' => route('admin.users.show', $user),
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
