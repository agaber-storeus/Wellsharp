@php
    $providerLocations = $providers->mapWithKeys(fn ($provider) => [(string) $provider->id => $provider->locations->map(fn ($location) => ['id' => (string) $location->id, 'location' => $location->location])->values()->all()]);
@endphp
<div class="admin-bento" x-data="{ providerId: @js((string) old('training_provider_id', $schedule->training_provider_id)), locationId: @js((string) old('training_provider_location_id', $schedule->training_provider_location_id)), providerLocations: @js($providerLocations), locations() { return this.providerLocations[this.providerId] || []; }, syncLocation() { const options = this.locations(); this.locationId = options.length === 1 ? options[0].id : ''; }, init() { if (!this.locations().some(location => location.id === this.locationId)) this.syncLocation(); } }"><div class="admin-bento-card admin-bento-card--wide">
    <div class="admin-card-head"><span class="admin-card-icon">🗓️</span><h3>Schedule details</h3></div>
    <div class="admin-field-grid">
    <div class="field full">
        <x-admin.label for="exam_id" required>Exam</x-admin.label>
        <select id="exam_id" name="exam_id" required>
            <option value="">Select an exam</option>
            @foreach($exams as $examOption)
                <option value="{{ $examOption->id }}" @selected((string) old('exam_id', $selectedExamId ?? $schedule->exam_id) === (string) $examOption->id)>
                    {{ $examOption->name }} — {{ $examOption->subject->name }} ({{ $examOption->questions_count }} questions)
                </option>
            @endforeach
        </select>
        <small class="muted">Only published exams can be scheduled. This same record is shown as a Class in the Proctor, Instructor, and Student interfaces.</small>
    </div>
    <div class="field full">
        <x-admin.label for="group_id" required>Group</x-admin.label>
        <select id="group_id" name="group_id" required>
            <option value="">Select a group</option>
            @foreach($groups as $groupOption)
                <option value="{{ $groupOption->id }}" @selected((string) old('group_id', $schedule->group_id) === (string) $groupOption->id)>{{ $groupOption->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field full"><x-admin.label for="training_provider_id">Training provider</x-admin.label><select id="training_provider_id" name="training_provider_id" x-model="providerId" x-on:change="syncLocation()"><option value="">Not assigned</option>@foreach($providers as $provider)<option value="{{ $provider->id }}">{{ $provider->name }}</option>@endforeach</select><small class="muted">This provider belongs to this scheduled exam and may differ between Groups.</small></div>
    <div class="field full" x-show="providerId && locations().length > 1" x-cloak><x-admin.label for="training_provider_location_id" required>Location</x-admin.label><select id="training_provider_location_id" name="training_provider_location_id" x-model="locationId" x-bind:required="providerId && locations().length > 1"><option value="">Select a location</option><template x-for="location in locations()" :key="location.id"><option x-bind:value="location.id" x-text="location.location"></option></template></select></div>
    <input x-show="providerId && locations().length === 1" type="hidden" name="training_provider_location_id" x-bind:value="locationId">
    <div class="field full muted" x-show="providerId && locations().length === 0" x-cloak>The selected provider has no active locations.</div>
    <div class="field"><x-admin.label for="start_date" required>Start date</x-admin.label><input id="start_date" type="date" name="start_date" value="{{ old('start_date', $schedule->start_date?->format('Y-m-d')) }}" required><small class="muted">The first date when students can start the exam.</small></div>
    <div class="field"><x-admin.label for="start_time">Start time</x-admin.label><input id="start_time" type="time" name="start_time" value="{{ old('start_time', $schedule->start_time ? substr($schedule->start_time, 0, 5) : '') }}"><small class="muted">Leave blank to allow from 12:00 AM.</small></div>
    <div class="field"><x-admin.label for="end_date" required>End date</x-admin.label><input id="end_date" type="date" name="end_date" value="{{ old('end_date', $schedule->end_date?->format('Y-m-d')) }}" required><small class="muted">The last date when students can start the exam.</small></div>
    <div class="field"><x-admin.label for="end_time">End time</x-admin.label><input id="end_time" type="time" name="end_time" value="{{ old('end_time', $schedule->end_time ? substr($schedule->end_time, 0, 5) : '') }}"><small class="muted">Leave blank to allow through 11:59 PM.</small></div>
    <div class="field full"><x-admin.label for="duration_minutes" required>Exam duration (minutes)</x-admin.label><input id="duration_minutes" type="number" name="duration_minutes" min="1" required value="{{ old('duration_minutes', $schedule->duration_minutes) }}"><small class="muted">Each student gets this many minutes from the moment they start. This is the only exam timer; questions do not have individual timers.</small></div>
    <div class="field full"><x-admin.label for="start_mode" required>Exam start mechanism</x-admin.label><select id="start_mode" name="start_mode" required><option value="automatic" @selected(old('start_mode', $schedule->start_mode?->value ?? 'automatic') === 'automatic')>Automatic — follow start/end dates</option><option value="manual" @selected(old('start_mode', $schedule->start_mode?->value ?? 'automatic') === 'manual')>Manual — Proctor or Proctor ID</option></select><small class="muted">This setting applies to this Group. Manual schedules do not start when the start date arrives.</small></div>
    <div class="field"><x-admin.label for="proctor_id" required>Proctor</x-admin.label><select id="proctor_id" name="proctor_id" required><option value="">Select Proctor</option>@foreach($proctors as $proctor)<option value="{{ $proctor->id }}" @selected((string) old('proctor_id', $schedule->trainingClass?->proctor_id) === (string) $proctor->id)>{{ $proctor->wellsharp_id }} - {{ $proctor->display_name }}</option>@endforeach</select><small class="muted">This same record is shown as a Class in the Proctor's interface.</small></div>
    <div class="field"><x-admin.label for="instructor_id" required>Instructor</x-admin.label><select id="instructor_id" name="instructor_id" required><option value="">Select Instructor</option>@foreach($instructors as $instructor)<option value="{{ $instructor->id }}" @selected((string) old('instructor_id', $schedule->trainingClass?->instructor_id) === (string) $instructor->id)>{{ $instructor->wellsharp_id }} - {{ $instructor->display_name }}</option>@endforeach</select></div>
    </div>
</div></div>
<div class="actions" style="margin-top:20px"><button class="btn">Save schedule</button><a class="btn secondary" href="{{ route('admin.exam-schedules.index') }}">Cancel</a></div>
