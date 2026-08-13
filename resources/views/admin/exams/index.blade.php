@extends('layouts.admin')

@section('admin-content')
<div class="page-head"><div><span class="admin-kicker">Subject &middot; {{ $course->code }}</span><h1>Exams</h1><p>{{ $course->name }}</p></div><div class="actions"><a class="btn secondary" href="{{ route('admin.courses.show', $course) }}">Subject</a><a class="btn" href="{{ route('admin.courses.exams.create', $course) }}">Create exam</a></div></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Code</th><th>Questions</th><th>Schedules</th><th>Mode</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($exams as $exam)
    <tr x-data="examRow(@js(route('admin.exams.archive', $exam)), @js($exam->status->value))"><td><a href="{{ route('admin.exams.show', $exam) }}">{{ $exam->name }}</a></td><td>{{ $exam->code ?: '-' }}</td><td>{{ $exam->questions_count }}</td><td>{{ $exam->schedules_count }}</td><td>{{ $exam->question_order_mode->label() }}</td><td><span class="badge" x-bind:class="status" x-text="status === 'archived' ? 'Archived' : @js($exam->status->label())"></span></td><td><div class="exam-actions"><a href="{{ route('admin.exams.show', $exam) }}">View</a><template x-if="status !== 'archived'"><button class="btn secondary small exam-status-toggle" type="button" x-on:click.prevent="archive()" x-bind:disabled="archiving" x-bind:aria-busy="archiving ? 'true' : 'false'" x-text="archiving ? 'Updating...' : 'Archive'"></button></template><span class="exam-row-error" x-show="error" x-text="error" x-cloak></span></div></td></tr>
@empty
    <tr><td colspan="7" class="muted">No exams found.</td></tr>
@endforelse
</tbody></table></div>{{ $exams->links('components.pagination') }}</div>
<style>[x-cloak]{display:none!important}.exam-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.exam-status-toggle{cursor:pointer!important;pointer-events:auto!important;touch-action:manipulation}.exam-row-error{display:block;color:#b42318;font-size:12px;margin-top:5px}</style>
<script>
    window.examRow = function (endpoint, initialStatus) {
        return {
            endpoint,
            status: initialStatus,
            archiving: false,
            error: '',
            async archive() {
                if (this.status === 'archived' || this.archiving) return;
                this.archiving = true;
                this.error = '';
                try {
                    const response = await fetch(this.endpoint, { method: 'PATCH', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' } });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(payload.message || 'Exam could not be archived.');
                    this.status = payload.status || 'archived';
                } catch (exception) {
                    this.error = exception.message || 'Exam could not be archived.';
                } finally {
                    this.archiving = false;
                }
            }
        };
    };
</script>
@endsection
