<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ChangeUserRoleAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DisableUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeUserRoleRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
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
        $user = $action->execute($request->validated());

        return redirect()->route('admin.users.show', $user)->with('status', 'User created successfully.');
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

    public function disable(User $user, DisableUserAction $action): RedirectResponse
    {
        $this->authorize('delete', $user);
        abort_if($user->is(auth()->user()), 422, 'You cannot disable your own account.');
        $action->execute($user);

        return back()->with('status', 'User disabled and existing sessions revoked.');
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
            'proctor_id' => $user->examControlCredential?->control_id,
            'role' => $user->currentRole?->name ?: 'Unassigned',
            'status' => $user->status->value,
            'status_label' => $user->status->label(),
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
