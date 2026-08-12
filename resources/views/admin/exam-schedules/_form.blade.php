<div class="form-grid">
    <div class="field full">
        <label for="exam_id">Exam</label>
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
        <label for="group_id">Group</label>
        <select id="group_id" name="group_id" required>
            <option value="">Select a group</option>
            @foreach($groups as $groupOption)
                <option value="{{ $groupOption->id }}" @selected((string) old('group_id', $schedule->group_id) === (string) $groupOption->id)>{{ $groupOption->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label for="start_date">Start date</label><input id="start_date" type="date" name="start_date" value="{{ old('start_date', $schedule->start_date?->format('Y-m-d')) }}" required><small class="muted">The first date when students can start the exam.</small></div>
    <div class="field"><label for="end_date">End date</label><input id="end_date" type="date" name="end_date" value="{{ old('end_date', $schedule->end_date?->format('Y-m-d')) }}" required><small class="muted">The last date when students can start the exam.</small></div>
    <div class="field full"><label for="duration_minutes">Exam duration (minutes)</label><input id="duration_minutes" type="number" name="duration_minutes" min="1" required value="{{ old('duration_minutes', $schedule->duration_minutes) }}"><small class="muted">Each student gets this many minutes from the moment they start. This is the only exam timer; questions do not have individual timers.</small></div>
</div>
<div class="actions" style="margin-top:20px"><button class="btn">Save schedule</button><a class="btn secondary" href="{{ route('admin.exam-schedules.index') }}">Cancel</a></div>
