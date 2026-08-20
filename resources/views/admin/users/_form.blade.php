@php
    $selectedRole = (string) old('role_id', $user->current_role_id ?: (($studentOnly ?? false) ? $studentRoleId : ''));
    $studentRole = (string) $studentRoleId;
    $proctorRole = (string) ($proctorRoleId ?? \App\Models\Role::where('key', \App\Models\Role::PROCTOR)->value('id'));
    $initialGroups = collect($selectedGroupOptions ?? [])->map(fn ($group): array => ['id' => $group->id, 'name' => $group->name, 'code' => $group->code])->values()->all();
    $currentGender = old('gender', $user->profile?->gender);
    $initialBirthday = old('birthday', $user->profile?->birthday?->format('Y-m-d'));
    $initialAge = \App\Models\UserProfile::calculateAge($initialBirthday);
@endphp

<style>
    [x-cloak]{display:none!important}.group-picker-search{display:flex;align-items:center;gap:12px}.group-picker-search input{flex:1}.group-picker-results{display:grid;gap:6px;margin-top:8px;max-height:240px;overflow-y:auto;border:1px solid var(--admin-line,#d9e1ea);padding:8px;border-radius:6px}.group-picker-result{display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:center;text-align:left;border:0;background:var(--admin-surface-soft,#f7f9fb);padding:9px 10px;color:inherit;cursor:pointer;border-radius:10px}.group-picker-result:hover{background:var(--admin-accent-warm-soft,#eaf1f7)}.group-picker-result:disabled{opacity:.55;cursor:default}.group-picker-result small{color:var(--admin-muted,#718096)}.group-picker-result strong{color:var(--admin-blue)}.group-picker-selected{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.group-chip{display:inline-flex;align-items:center;gap:7px;background:var(--admin-accent-cool-soft,#e8f0f7);color:var(--admin-blue);border-radius:999px;padding:6px 10px}.group-chip button{border:0;background:transparent;color:inherit;font-size:18px;line-height:1;cursor:pointer}.group-picker-error{color:var(--admin-danger,#b42318);margin-top:8px}.role-note{margin:-8px 0 2px;color:var(--admin-muted);font-size:12px;line-height:1.5}
</style>

<div class="admin-bento" x-data="{
        selectedRole: @js($selectedRole), studentRole: @js($studentRole), proctorRole: @js($proctorRole),
        age: @js($initialAge),
        calcAge(value) {
            if (!value) { return ''; }
            const dob = new Date(value);
            if (Number.isNaN(dob.getTime())) { return ''; }
            const today = new Date();
            let years = today.getFullYear() - dob.getFullYear();
            const hadBirthdayThisYear = (today.getMonth() > dob.getMonth()) || (today.getMonth() === dob.getMonth() && today.getDate() >= dob.getDate());
            if (!hadBirthdayThisYear) { years--; }
            return years >= 0 ? years : '';
        }
    }">
    @if($studentOnly ?? false)
        <input type="hidden" name="role_id" value="{{ $studentRoleId }}">
    @elseif($user->exists && $user->current_role_id)
        <input type="hidden" name="role_id" value="{{ $user->current_role_id }}">
    @endif

    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon">🪪</span><h3>Identity</h3></div>
        <div class="admin-field-grid">
            <div class="field"><x-admin.label for="first_name" required>First name</x-admin.label><input id="first_name" name="first_name" value="{{ old('first_name', $user->profile?->first_name) }}" required></div>
            <div class="field"><x-admin.label for="last_name" required>Last name</x-admin.label><input id="last_name" name="last_name" value="{{ old('last_name', $user->profile?->last_name) }}" required></div>
            <div class="field"><x-admin.label for="wellsharp_id">WellSharp ID</x-admin.label><input id="wellsharp_id" name="wellsharp_id" value="{{ old('wellsharp_id', $user->wellsharp_id) }}" {{ $user->exists ? 'readonly' : '' }}>@unless($user->exists)<small class="muted"><span class="admin-badge-generated">Auto-generated</span> Leave blank to generate from the first/last name below.</small>@endunless</div>
            @if($user->exists)
                <div class="field"><x-admin.label>Username</x-admin.label><input value="{{ $user->username }}" readonly><small class="muted"><span class="admin-badge-generated">System-generated</span> Display-only; not used to log in.</small></div>
            @endif
            @if(!$user->exists && !($studentOnly ?? false))
                <div class="field"><x-admin.label for="role_id" required>Role</x-admin.label><select id="role_id" name="role_id" x-model="selectedRole" required><option value="">Select role</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></div>
            @endif
        </div>
    </div>

    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon">✉️</span><h3>Contact</h3></div>
        <div class="admin-field-grid">
            <div class="field"><x-admin.label for="email">Email</x-admin.label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}"></div>
            <div class="field"><x-admin.label for="phone">Phone number</x-admin.label><input id="phone" name="phone" value="{{ old('phone', $user->profile?->phone) }}"></div>
            <div class="field"><x-admin.label for="birthday">Date of birth</x-admin.label><input id="birthday" type="date" name="birthday" value="{{ $initialBirthday }}" x-on:input="age = calcAge($event.target.value)"></div>
        </div>
    </div>

    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon cool">📍</span><h3>Location</h3></div>
        <div class="admin-field-grid">
            <div class="field" x-show="selectedRole !== studentRole" x-cloak><x-admin.label for="state">State / Province</x-admin.label><input id="state" name="state" value="{{ old('state', $user->profile?->state) }}"></div>
            <div class="field"><x-admin.label for="country">Country</x-admin.label><input id="country" name="country" value="{{ old('country', $user->profile?->country) }}"></div>
            <div class="field"><x-admin.label for="city">City</x-admin.label><input id="city" name="city" value="{{ old('city', $user->profile?->city) }}"></div>
            <div class="field"><x-admin.label for="postal_code">Postal code</x-admin.label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $user->profile?->postal_code) }}"></div>
            <div class="field full"><x-admin.label for="address">Address</x-admin.label><input id="address" name="address" value="{{ old('address', $user->profile?->address) }}"></div>
        </div>
    </div>

    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon cool">💼</span><h3>Work details</h3></div>
        <div class="admin-field-grid">
            <div class="field"><x-admin.label for="company">Company</x-admin.label><input id="company" name="company" value="{{ old('company', $user->profile?->company) }}"></div>
            <div class="field"><x-admin.label for="position">Position</x-admin.label><input id="position" name="position" value="{{ old('position', $user->profile?->position) }}"></div>
            <div class="field"><x-admin.label for="employee_id">Employee ID</x-admin.label><input id="employee_id" name="employee_id" value="{{ old('employee_id', $user->profile?->employee_id) }}"></div>
            <div class="field" x-show="selectedRole === studentRole" x-cloak><x-admin.label for="company_contact">Company contact</x-admin.label><input id="company_contact" name="company_contact" value="{{ old('company_contact', $user->profile?->company_contact) }}"></div>
        </div>
    </div>

    <div class="admin-bento-card" x-show="selectedRole === studentRole" x-cloak>
        <div class="admin-card-head"><span class="admin-card-icon">🎓</span><h3>Student profile</h3></div>
        <div class="admin-field-grid">
            <div class="field"><x-admin.label for="age">Age <small class="muted">(auto-calculated from date of birth)</small></x-admin.label><input id="age" type="number" x-bind:value="age" readonly tabindex="-1"></div>
            <div class="field"><x-admin.label for="gender">Gender <small class="muted">(optional)</small></x-admin.label><select id="gender" name="gender"><option value="">Select gender</option>@foreach(\App\Enums\Gender::cases() as $gender)<option value="{{ $gender->value }}" @selected($currentGender === $gender->value)>{{ $gender->value }}</option>@endforeach</select></div>
        </div>
    </div>

    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon">🔐</span><h3>Security</h3></div>
        <div class="admin-field-grid">
            <div class="field"><x-admin.label for="password">{{ $user->exists ? 'New password' : 'Initial password' }} <small class="muted">(optional)</small></x-admin.label><input id="password" type="password" name="password" autocomplete="new-password"><small class="muted" x-text="(selectedRole === studentRole ? 'Exactly 5 characters.' : '5-8 characters.') + ' {{ $user->exists ? 'Leave blank to keep the current password.' : 'Leave blank to auto-generate a secure temporary password, shown once after saving.' }}'"></small></div>
            <div class="field"><x-admin.label for="password_confirmation">Confirm password</x-admin.label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"></div>
        </div>
    </div>

    <div class="admin-bento-card admin-bento-card--wide" x-show="selectedRole === proctorRole" x-cloak>
        <div class="admin-card-head"><span class="admin-card-icon cool">🛡️</span><h3>Proctor's ID</h3></div>
        @if($user->examControlCredential?->control_id)
            <div class="field"><input value="{{ $user->examControlCredential?->control_id }}" readonly aria-describedby="proctor-id-note">
            <small id="proctor-id-note" class="role-note">This system-generated Proctor's ID is used to authorize Class start and end controls. It cannot be edited.</small></div>
        @else
            <p class="role-note"><span class="admin-badge-generated">Auto-generated</span> A unique Proctor's ID will be generated automatically after this user is created.</p>
        @endif
    </div>

    <div class="admin-bento-card admin-bento-card--wide" x-show="selectedRole === studentRole" x-cloak x-data="studentGroupPicker(@js($initialGroups), @js(route('admin.groups.search')))">
        <div class="admin-card-head"><span class="admin-card-icon">👥</span><h3>Assigned Groups</h3></div>
        <p class="admin-card-note">Optional; search and select one or more.</p>
        <div class="group-picker-search"><input x-model="query" x-on:input.debounce.300ms="searchGroups()" type="search" placeholder="Type at least 2 characters to search Groups" autocomplete="off"><span class="muted" x-show="loading">Searching...</span></div>
        <div class="group-picker-error" x-show="error" x-text="error" x-cloak></div>
        <div class="group-picker-results" x-show="results.length" x-cloak><template x-for="group in results" :key="group.id"><button type="button" class="group-picker-result" x-on:click="select(group)" x-bind:disabled="isSelected(group.id)"><span x-text="group.name"></span><small x-text="group.code || 'No code'"></small><strong x-show="isSelected(group.id)">Selected</strong></button></template></div>
        <p class="muted" x-show="query.trim().length >= 2 && !loading && !error && results.length === 0" x-cloak>No matching active Groups found.</p>
        <div class="group-picker-selected"><template x-for="group in selected" :key="group.id"><span class="group-chip"><span x-text="group.name"></span><button type="button" x-on:click="remove(group.id)" aria-label="Remove selected Group">×</button><input type="hidden" name="group_ids[]" x-bind:value="group.id"></span></template></div>
    </div>
</div>
<div class="actions"><button class="btn" type="submit">Save user</button><a class="btn secondary" href="{{ ($studentOnly ?? false) ? route('admin.students.index') : ($user->exists ? route('admin.users.show', $user) : route('admin.users.index')) }}">Cancel</a></div>
