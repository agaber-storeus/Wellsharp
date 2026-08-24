@extends('layouts.admin')
@section('admin-content')
<div class="page-head hero">
    <div><span class="admin-kicker">Training class</span><h1>{{ $trainingClass->class_number }}</h1><p>{{ $trainingClass->course->name }} · {{ $trainingClass->status->label() }}</p></div>
    <div class="admin-page-actions"><a class="btn secondary" href="{{ route('admin.classes.edit', $trainingClass) }}">Edit</a>@if(!in_array($trainingClass->status->value, ['completed', 'cancelled']))<form method="POST" action="{{ route('admin.classes.cancel', $trainingClass) }}">@csrf @method('PATCH')<button class="btn danger">Cancel class</button></form>@endif</div>
</div>

<div class="admin-bento">

    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon">📋</span><h3>Class details</h3><span class="badge {{ $trainingClass->status->value }}" style="margin-left:auto">{{ $trainingClass->status->label() }}</span></div>
        <div class="admin-meta-grid">
            <div class="admin-meta-item"><span class="muted">Subject</span><strong>{{ $trainingClass->course->code }} - {{ $trainingClass->course->name }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Provider</span><strong>{{ $trainingClass->provider?->name ?: 'Not assigned' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Proctor</span><strong>{{ $trainingClass->proctor?->display_name ?: 'Not assigned' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Instructor</span><strong>{{ $trainingClass->instructor?->display_name ?: 'Not assigned' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Schedule</span><strong>{{ $trainingClass->starts_at?->format('M j, Y H:i') ?: 'Not scheduled' }} @if($trainingClass->ends_at)- {{ $trainingClass->ends_at->format('M j, Y H:i') }}@endif</strong></div>
        </div>
    </div>

    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon cool">🛡️</span><h3>Class control</h3></div>
        <p class="admin-card-note">Only this Class's assigned Proctor ({{ $trainingClass->proctor?->display_name ?: 'not assigned' }}) may start or end it directly. Its assigned Instructor ({{ $trainingClass->instructor?->display_name ?: 'not assigned' }}) may do so by entering an active Proctor's ID.</p>
    </div>

    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon">👥</span><h3>Enroll a Student</h3></div>
        <form class="actions" method="POST" action="{{ route('admin.classes.enrollments.store', $trainingClass) }}">@csrf<select name="student_user_id" required><option value="">Select Student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->wellsharp_id }} - {{ $student->display_name }}</option>@endforeach</select><button class="btn">Enroll</button></form>
        <div class="table-wrap"><table class="table"><thead><tr><th>Student</th><th>Status</th><th>Enrolled</th><th></th></tr></thead><tbody>@forelse($trainingClass->enrollments as $enrollment)<tr><td>{{ $enrollment->student->display_name }}<br><span class="muted">{{ $enrollment->student->wellsharp_id }}</span></td><td><span class="badge {{ $enrollment->status->value }}">{{ $enrollment->status->label() }}</span></td><td>{{ $enrollment->enrolled_at?->format('M j, Y') }}</td><td>@if($enrollment->status->value === 'enrolled')<form method="POST" action="{{ route('admin.classes.enrollments.withdraw', [$trainingClass, $enrollment]) }}">@csrf @method('PATCH')<button class="btn danger small">Withdraw</button></form>@endif</td></tr>@empty<tr><td colspan="4"><div class="admin-empty-row"><span class="admin-empty-row-icon">🔍</span>No Students enrolled.</div></td></tr>@endforelse</tbody></table></div>
    </div>

</div>
@endsection
