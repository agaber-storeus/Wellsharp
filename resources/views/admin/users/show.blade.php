@extends('layouts.admin')
@section('admin-content')
<style>.profile-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.profile-grid>div{display:flex;flex-direction:column;gap:5px;min-width:0}.profile-grid strong{font-weight:600;overflow-wrap:anywhere}.profile-grid small{line-height:1.4}@media(max-width:900px){.profile-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.profile-grid{grid-template-columns:1fr}}</style>
@php
    $profile = $user->profile;
    $role = $user->currentRole?->key;
    $value = fn (?string $item): string => filled($item) ? $item : 'Not set';
@endphp

<div class="page-head">
    <div><span class="admin-kicker">User profile</span><h1>{{ $user->display_name }}</h1><p>{{ $user->currentRole?->name ?: 'Unassigned' }} · {{ $user->wellsharp_id }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.users.edit', $user) }}">Edit profile</a>@if($user->isActive() && !$user->is(auth()->user()))<form method="POST" action="{{ route('admin.users.disable', $user) }}">@csrf @method('PATCH')<button class="btn danger" type="submit">Disable account</button></form>@endif</div>
</div>

<div class="card">
    <div class="admin-section-head"><h2>Account</h2><span class="badge {{ $user->status->value }}">{{ $user->status->label() }}</span></div>
    <div class="profile-grid">
        <div><span class="muted">First name</span><strong>{{ $value($profile?->first_name) }}</strong></div>
        <div><span class="muted">Last name</span><strong>{{ $value($profile?->last_name) }}</strong></div>
        <div><span class="muted">WellSharp ID</span><strong>{{ $user->wellsharp_id }}</strong></div>
        <div><span class="muted">Email</span><strong>{{ $value($user->email) }}</strong></div>
        <div><span class="muted">Date of birth</span><strong>{{ $profile?->birthday?->format('Y-m-d') ?: 'Not set' }}</strong></div>
        <div><span class="muted">Phone number</span><strong>{{ $value($profile?->phone) }}</strong></div>
        <div><span class="muted">Role</span><strong>{{ $user->currentRole?->name ?: 'Unassigned' }}</strong></div>
        <div><span class="muted">Password</span><strong>Stored securely</strong><small class="muted">Passwords are never displayed.</small></div>
        @if($role === \App\Models\Role::PROCTOR)
            <div><span class="muted">Exam-control ID</span><strong>{{ $value($user->examControlCredential?->control_id) }}</strong><small class="muted">Used to authorize Class start and end controls.</small></div>
        @endif
    </div>
</div>

<div class="card">
    <div class="admin-section-head"><h2>Profile details</h2><span class="muted">{{ $role === \App\Models\Role::STUDENT ? 'Student profile' : 'Staff profile' }}</span></div>
    <div class="profile-grid">
        <div><span class="muted">Address</span><strong>{{ $value($profile?->address) }}</strong></div>
        <div><span class="muted">Country</span><strong>{{ $value($profile?->country) }}</strong></div>
        @if($role !== \App\Models\Role::STUDENT)<div><span class="muted">State / Province</span><strong>{{ $value($profile?->state) }}</strong></div>@endif
        <div><span class="muted">City</span><strong>{{ $value($profile?->city) }}</strong></div>
        <div><span class="muted">Postal code</span><strong>{{ $value($profile?->postal_code) }}</strong></div>
        <div><span class="muted">Company</span><strong>{{ $value($profile?->company) }}</strong></div>
        <div><span class="muted">Position</span><strong>{{ $value($profile?->position) }}</strong></div>
        <div><span class="muted">Employee ID</span><strong>{{ $value($profile?->employee_id) }}</strong></div>
        @if($role === \App\Models\Role::STUDENT)
            <div><span class="muted">Company contact</span><strong>{{ $value($profile?->company_contact) }}</strong></div>
            <div><span class="muted">Gender</span><strong>{{ $value($profile?->gender) }}</strong></div>
            <div><span class="muted">Age</span><strong>{{ $profile?->age ?: 'Not set' }}</strong></div>
        @endif
    </div>
</div>

<div class="card"><div class="admin-section-head"><h2>Assigned Groups</h2>@if($role === \App\Models\Role::STUDENT)<span class="badge active">{{ $user->groups->count() }} assigned</span>@endif</div>@if($role === \App\Models\Role::STUDENT)<div class="check-grid">@forelse($user->groups as $group)<a href="{{ route('admin.groups.show', $group) }}">{{ $group->name }} <small class="muted">({{ $group->code ?: 'No code' }})</small></a>@empty<span class="muted">This Student is not assigned to any Groups.</span>@endforelse</div>@else<p class="muted">Groups are available only for Students.</p>@endif</div>

<div class="card"><h2>Change role</h2><p class="muted">Changing a role closes the previous role history, creates an exam-control ID for eligible staff when needed, and revokes existing sessions.</p><form class="actions" method="POST" action="{{ route('admin.users.role', $user) }}">@csrf @method('PATCH')<select name="role_id" required>@foreach(\App\Models\Role::orderBy('name')->get() as $availableRole)<option value="{{ $availableRole->id }}" @selected($user->current_role_id === $availableRole->id)>{{ $availableRole->name }}</option>@endforeach</select><button class="btn" type="submit">Change role</button></form></div>
@endsection
