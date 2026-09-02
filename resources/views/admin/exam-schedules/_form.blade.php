<div class="admin-bento"><div class="admin-bento-card admin-bento-card--wide">
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
    <div class="field full"><x-admin.label for="training_provider_id">Training provider / location</x-admin.label><select id="training_provider_id" name="training_provider_id"><option value="">Not assigned</option>@foreach($providers as $provider)<option value="{{ $provider->id }}" @selected((string) old('training_provider_id', $schedule->training_provider_id) === (string) $provider->id)>{{ $provider->name }}</option>@endforeach</select><small class="muted">This provider belongs to this scheduled exam and may differ between Groups.</small></div>
    <div class="field"><x-admin.label for="start_date" required>Start date</x-admin.label><input id="start_date" type="date" name="start_date" value="{{ old('start_date', $schedule->start_date?->format('Y-m-d')) }}" required><small class="muted">The first date when students can start the exam.</small></div>
    <div class="field"><x-admin.label for="end_date" required>End date</x-admin.label><input id="end_date" type="date" name="end_date" value="{{ old('end_date', $schedule->end_date?->format('Y-m-d')) }}" required><small class="muted">The last date when students can start the exam.</small></div>
    <div class="field full"><x-admin.label for="duration_minutes" required>Exam duration (minutes)</x-admin.label><input id="duration_minutes" type="number" name="duration_minutes" min="1" required value="{{ old('duration_minutes', $schedule->duration_minutes) }}"><small class="muted">Each student gets this many minutes from the moment they start. This is the only exam timer; questions do not have individual timers.</small></div>
    <div class="field full"><x-admin.label for="start_mode" required>Exam start mechanism</x-admin.label><select id="start_mode" name="start_mode" required><option value="automatic" @selected(old('start_mode', $schedule->start_mode?->value ?? 'automatic') === 'automatic')>Automatic — follow start/end dates</option><option value="manual" @selected(old('start_mode', $schedule->start_mode?->value ?? 'automatic') === 'manual')>Manual — Proctor or Proctor ID</option></select><small class="muted">This setting applies to this Group. Manual schedules do not start when the start date arrives.</small></div>
    <div class="field"><x-admin.label for="proctor_id" required>Proctor</x-admin.label><select id="proctor_id" name="proctor_id" required><option value="">Select Proctor</option>@foreach($proctors as $proctor)<option value="{{ $proctor->id }}" @selected((string) old('proctor_id', $schedule->trainingClass?->proctor_id) === (string) $proctor->id)>{{ $proctor->wellsharp_id }} - {{ $proctor->display_name }}</option>@endforeach</select><small class="muted">This same record is shown as a Class in the Proctor's interface.</small></div>
    <div class="field"><x-admin.label for="instructor_id" required>Instructor</x-admin.label><select id="instructor_id" name="instructor_id" required><option value="">Select Instructor</option>@foreach($instructors as $instructor)<option value="{{ $instructor->id }}" @selected((string) old('instructor_id', $schedule->trainingClass?->instructor_id) === (string) $instructor->id)>{{ $instructor->wellsharp_id }} - {{ $instructor->display_name }}</option>@endforeach</select></div>
    </div>
</div></div>
<div class="actions" style="margin-top:20px"><button class="btn">Save schedule</button><a class="btn secondary" href="{{ route('admin.exam-schedules.index') }}">Cancel</a></div>
