@php
    $selectedRole = (string) old('role_id', $user->current_role_id ?: (($studentOnly ?? false) ? $studentRoleId : ''));
    $studentRole = (string) $studentRoleId;
    $proctorRole = (string) ($proctorRoleId ?? \App\Models\Role::where('key', \App\Models\Role::PROCTOR)->value('id'));
    $instructorRole = (string) \App\Models\Role::where('key', \App\Models\Role::INSTRUCTOR)->value('id');
    $initialGroups = collect($selectedGroupOptions ?? [])->map(fn ($group): array => ['id' => $group->id, 'name' => $group->name, 'code' => $group->code])->values()->all();
    $currentGender = old('gender', $user->profile?->gender);
@endphp

<style>
    [x-cloak]{display:none!important}.group-picker-search{display:flex;align-items:center;gap:12px}.group-picker-search input{flex:1}.group-picker-results{display:grid;gap:6px;margin-top:8px;max-height:240px;overflow-y:auto;border:1px solid var(--admin-border,#d9e1ea);padding:8px;border-radius:6px}.group-picker-result{display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:center;text-align:left;border:0;background:#f7f9fb;padding:9px 10px;color:inherit;cursor:pointer}.group-picker-result:hover{background:#eaf1f7}.group-picker-result:disabled{opacity:.55;cursor:default}.group-picker-result small{color:#718096}.group-picker-result strong{color:var(--admin-blue)}.group-picker-selected{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.group-chip{display:inline-flex;align-items:center;gap:7px;background:#e8f0f7;color:var(--admin-blue);border-radius:16px;padding:6px 10px}.group-chip button{border:0;background:transparent;color:inherit;font-size:18px;line-height:1;cursor:pointer}.group-picker-error{color:#b42318;margin-top:8px}.role-note{margin:-8px 0 2px;color:var(--admin-muted);font-size:12px;line-height:1.5}
</style>

<div class="form-grid" x-data="{ selectedRole: @js($selectedRole), studentRole: @js($studentRole), proctorRole: @js($proctorRole), instructorRole: @js($instructorRole) }">
    <div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" value="{{ old('first_name', $user->profile?->first_name) }}" required></div>
    <div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name', $user->profile?->last_name) }}" required></div>
    <div class="field"><label for="wellsharp_id">WellSharp ID</label><input id="wellsharp_id" name="wellsharp_id" value="{{ old('wellsharp_id', $user->wellsharp_id) }}" {{ $user->exists ? 'readonly' : 'required' }}></div>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}"></div>
    <div class="field"><label for="birthday">Date of birth</label><input id="birthday" type="date" name="birthday" value="{{ old('birthday', $user->profile?->birthday?->format('Y-m-d')) }}"></div>
    <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" value="{{ old('phone', $user->profile?->phone) }}"></div>

    @if(!$user->exists && !($studentOnly ?? false))
        <div class="field"><label for="role_id">Role</label><select id="role_id" name="role_id" x-model="selectedRole" required><option value="">Select role</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></div>
    @elseif($studentOnly ?? false)
        <input type="hidden" name="role_id" value="{{ $studentRoleId }}">
    @elseif($user->current_role_id)
        <input type="hidden" name="role_id" value="{{ $user->current_role_id }}">
    @endif

    <div class="field" x-show="selectedRole !== studentRole" x-cloak><label for="state">State / Province</label><input id="state" name="state" value="{{ old('state', $user->profile?->state) }}"></div>
    <div class="field"><label for="country">Country</label><input id="country" name="country" value="{{ old('country', $user->profile?->country) }}"></div>
    <div class="field"><label for="city">City</label><input id="city" name="city" value="{{ old('city', $user->profile?->city) }}"></div>
    <div class="field"><label for="postal_code">Postal code</label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $user->profile?->postal_code) }}"></div>
    <div class="field full"><label for="address">Address</label><input id="address" name="address" value="{{ old('address', $user->profile?->address) }}"></div>
    <div class="field"><label for="company">Company</label><input id="company" name="company" value="{{ old('company', $user->profile?->company) }}"></div>
    <div class="field"><label for="position">Position</label><input id="position" name="position" value="{{ old('position', $user->profile?->position) }}"></div>
    <div class="field"><label for="employee_id">Employee ID</label><input id="employee_id" name="employee_id" value="{{ old('employee_id', $user->profile?->employee_id) }}"></div>
    <div class="field" x-show="selectedRole === studentRole" x-cloak><label for="company_contact">Company contact</label><input id="company_contact" name="company_contact" value="{{ old('company_contact', $user->profile?->company_contact) }}"></div>

    <div class="field" x-show="selectedRole === studentRole" x-cloak><label for="age">Age <small class="muted">(optional)</small></label><input id="age" type="number" name="age" value="{{ old('age', $user->profile?->age) }}" min="1" max="120"></div>
    <div class="field" x-show="selectedRole === studentRole" x-cloak><label for="gender">Gender <small class="muted">(optional)</small></label><select id="gender" name="gender"><option value="">Select gender</option>@foreach(\App\Enums\Gender::cases() as $gender)<option value="{{ $gender->value }}" @selected($currentGender === $gender->value)>{{ $gender->value }}</option>@endforeach</select></div>

    <div class="field full" x-show="selectedRole === proctorRole || selectedRole === instructorRole" x-cloak>
        <label>Exam-control ID</label>
        @if($user->examControlCredential?->control_id)
            <input value="{{ $user->examControlCredential?->control_id }}" readonly aria-describedby="proctor-id-note">
            <small id="proctor-id-note" class="role-note">This system-generated ID is used to authorize Class start and end controls. It cannot be edited.</small>
        @else
            <p class="role-note">A unique exam-control ID will be generated automatically after this user is created.</p>
        @endif
    </div>

    <div class="field"><label for="password">{{ $user->exists ? 'New password (optional)' : 'Initial password' }}</label><input id="password" type="password" name="password" {{ $user->exists ? '' : 'required' }} autocomplete="new-password"><small class="muted">Minimum 12 characters. Passwords are securely hashed and never displayed after saving.</small></div>
    <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" {{ $user->exists ? '' : 'required' }} autocomplete="new-password"></div>

    <div class="field full" x-show="selectedRole === studentRole" x-cloak x-data="studentGroupPicker(@js($initialGroups), @js(route('admin.groups.search')))">
        <label>Assigned Groups <small class="muted">(optional; search and select one or more)</small></label>
        <div class="group-picker-search"><input x-model="query" x-on:input.debounce.300ms="searchGroups()" type="search" placeholder="Type at least 2 characters to search Groups" autocomplete="off"><span class="muted" x-show="loading">Searching...</span></div>
        <div class="group-picker-error" x-show="error" x-text="error" x-cloak></div>
        <div class="group-picker-results" x-show="results.length" x-cloak><template x-for="group in results" :key="group.id"><button type="button" class="group-picker-result" x-on:click="select(group)" x-bind:disabled="isSelected(group.id)"><span x-text="group.name"></span><small x-text="group.code || 'No code'"></small><strong x-show="isSelected(group.id)">Selected</strong></button></template></div>
        <p class="muted" x-show="query.trim().length >= 2 && !loading && !error && results.length === 0" x-cloak>No matching active Groups found.</p>
        <div class="group-picker-selected"><template x-for="group in selected" :key="group.id"><span class="group-chip"><span x-text="group.name"></span><button type="button" x-on:click="remove(group.id)" aria-label="Remove selected Group">×</button><input type="hidden" name="group_ids[]" x-bind:value="group.id"></span></template></div>
    </div>
</div>
<div class="actions" style="margin-top:20px"><button class="btn" type="submit">Save user</button><a class="btn secondary" href="{{ ($studentOnly ?? false) ? route('admin.students.index') : ($user->exists ? route('admin.users.show', $user) : route('admin.users.index')) }}">Cancel</a></div>
