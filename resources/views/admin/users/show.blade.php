@extends('layouts.admin')
@section('admin-content')
@php
    $profile = $user->profile;
    $role = $user->currentRole?->key;
    $value = fn (?string $item): string => filled($item) ? $item : 'Not set';
@endphp

<div class="admin-page-head hero">
    <div><span class="admin-kicker">User profile</span><h1>{{ $user->display_name }}</h1><p>{{ $user->currentRole?->name ?: 'Unassigned' }} · {{ $user->wellsharp_id }}</p></div>
    <div class="admin-page-actions"><a class="btn" href="{{ route('admin.users.edit', $user) }}">Edit profile</a>@if($user->isActive() && !$user->is(auth()->user()))<form method="POST" action="{{ route('admin.users.disable', $user) }}">@csrf @method('PATCH')<button class="btn danger" type="submit">Disable account</button></form>@endif</div>
</div>

<div class="admin-bento">

    @if(session('generated_password'))
        <div class="admin-bento-card admin-bento-card--wide" style="border-color:#e7cd8f;background:var(--admin-warning-soft)">
            <div class="admin-card-head"><span class="admin-card-icon">🔑</span><h3>Temporary password</h3></div>
            <p><strong style="font-family:ui-monospace,Consolas,monospace;font-size:16px">{{ session('generated_password') }}</strong></p>
            <p class="muted">Share this with {{ $user->display_name }} now &mdash; it will not be shown again and is not stored anywhere in plaintext.</p>
        </div>
    @endif

    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon">🪪</span><h3>Account</h3><span class="badge {{ $user->status->value }}" style="margin-left:auto">{{ $user->status->label() }}</span></div>
        <div class="admin-meta-grid">
            <div class="admin-meta-item"><span class="muted">First name</span><strong>{{ $value($profile?->first_name) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Last name</span><strong>{{ $value($profile?->last_name) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">WellSharp ID</span><strong>{{ $user->wellsharp_id }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Username</span><strong>{{ $value($user->username) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Email</span><strong>{{ $value($user->email) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Date of birth</span><strong>{{ $profile?->birthday?->format('Y-m-d') ?: 'Not set' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Phone number</span><strong>{{ $value($profile?->phone) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Role</span><strong>{{ $user->currentRole?->name ?: 'Unassigned' }}</strong></div>
            @if($role === \App\Models\Role::STUDENT && $user->password_ciphertext)
                <div class="admin-meta-item" x-data="{ revealed: null, revealing: false, error: '' }">
                    <span class="muted">Password</span>
                    <div x-show="!revealed" x-cloak>
                        <button type="button" class="btn secondary small" x-show="!revealing" x-on:click="
                            revealing = true; error = '';
                            fetch('{{ route('admin.users.reveal-password', $user) }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } })
                                .then(response => { if (!response.ok) throw new Error('Unable to reveal password.'); return response.json(); })
                                .then(data => { revealed = data.password; })
                                .catch(err => { error = err.message; })
                                .finally(() => { revealing = false; });
                        ">Reveal password</button>
                        <span x-show="revealing" x-cloak>Revealing…</span>
                    </div>
                    <strong x-show="revealed" x-text="revealed" x-cloak></strong>
                    <small class="muted" x-show="error" x-text="error" x-cloak></small>
                    <small class="muted" x-show="revealed" x-cloak>Visible only to you. This view was recorded in the audit log.</small>
                </div>
            @else
                <div class="admin-meta-item"><span class="muted">Password</span><strong>Stored securely</strong><small class="muted">Passwords are never displayed.</small></div>
            @endif
            @if($role === \App\Models\Role::PROCTOR)
                <div class="admin-meta-item"><span class="muted">Proctor's ID</span><strong>{{ $value($user->examControlCredential?->control_id) }}</strong><small class="muted">Used to authorize Class start and end controls.</small></div>
            @endif
        </div>
    </div>

    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon cool">📇</span><h3>Profile details</h3></div>
        <p class="admin-card-note">{{ $role === \App\Models\Role::STUDENT ? 'Student profile' : 'Staff profile' }}</p>
        <div class="admin-meta-grid">
            <div class="admin-meta-item"><span class="muted">Address</span><strong>{{ $value($profile?->address) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Country</span><strong>{{ $value($profile?->country) }}</strong></div>
            @if($role !== \App\Models\Role::STUDENT)<div class="admin-meta-item"><span class="muted">State / Province</span><strong>{{ $value($profile?->state) }}</strong></div>@endif
            <div class="admin-meta-item"><span class="muted">City</span><strong>{{ $value($profile?->city) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Postal code</span><strong>{{ $value($profile?->postal_code) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Company</span><strong>{{ $value($profile?->company) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Position</span><strong>{{ $value($profile?->position) }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Employee ID</span><strong>{{ $value($profile?->employee_id) }}</strong></div>
            @if($role === \App\Models\Role::STUDENT)
                <div class="admin-meta-item"><span class="muted">Company contact</span><strong>{{ $value($profile?->company_contact) }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Gender</span><strong>{{ $value($profile?->gender) }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Age</span><strong>{{ $profile?->age ?: 'Not set' }}</strong></div>
            @endif
        </div>
    </div>

    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon">👥</span><h3>Assigned Groups</h3>@if($role === \App\Models\Role::STUDENT)<span class="badge active" style="margin-left:auto">{{ $user->groups->count() }} assigned</span>@endif</div>
        @if($role === \App\Models\Role::STUDENT)
            <div class="check-grid">@forelse($user->groups as $group)<a href="{{ route('admin.groups.show', $group) }}">{{ $group->name }} <small class="muted">({{ $group->code ?: 'No code' }})</small></a>@empty<span class="muted">This Student is not assigned to any Groups.</span>@endforelse</div>
        @else
            <p class="muted">Groups are available only for Students.</p>
        @endif
    </div>

    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon cool">🔄</span><h3>Change role</h3></div>
        <p class="admin-card-note">Closes the previous role history, ensures a Proctor's ID exists when moving into the Proctor role, revokes it when moving out, and revokes existing sessions.</p>
        <form class="actions" method="POST" action="{{ route('admin.users.role', $user) }}">@csrf @method('PATCH')<select name="role_id" required>@foreach(\App\Models\Role::orderBy('name')->get() as $availableRole)<option value="{{ $availableRole->id }}" @selected($user->current_role_id === $availableRole->id)>{{ $availableRole->name }}</option>@endforeach</select><button class="btn" type="submit">Change role</button></form>
    </div>

</div>
@endsection
