@extends('layouts.admin')
@section('admin-content')
<style>[x-cloak]{display:none!important}.schedule-table-loading{opacity:.55;pointer-events:none}.schedule-sort{border:0;background:transparent;color:inherit;font:inherit;font-weight:600;cursor:pointer;padding:0}.schedule-sort:hover{color:var(--admin-blue)}.schedule-sort-icon{font-size:11px;margin-left:3px}.schedule-error{background:#fef3f2;color:#b42318;border-radius:5px;padding:10px 12px;margin-bottom:15px}.link-button{border:0;background:none;padding:0;color:var(--admin-blue);font:inherit;cursor:pointer}.link-button:hover{text-decoration:underline}</style>
<div class="page-head"><div><span class="admin-kicker">Exam Management</span><h1>Exam schedules</h1><p>Search, filter, and sort schedules without leaving this page.</p></div><a class="btn" href="{{ route('admin.exam-schedules.create') }}">Create schedule</a></div>
<div class="card" x-data="scheduleTable(@js(route('admin.exam-schedules.data')), @js($initialSchedules), @js($initialMeta))">
    <form class="search" x-on:submit.prevent="load(1)">
        <input x-model="search" x-on:input.debounce.350ms="load(1)" placeholder="Search exam, Subject, or Group" autocomplete="off">
        <select x-model="subjectId" x-on:change="load(1)"><option value="">All Subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select>
        <select x-model="examId" x-on:change="load(1)"><option value="">All Exams</option>@foreach($exams as $examOption)<option value="{{ $examOption->id }}">{{ $examOption->name }}</option>@endforeach</select>
        <select x-model="groupId" x-on:change="load(1)"><option value="">All Groups</option>@foreach($groups as $groupOption)<option value="{{ $groupOption->id }}">{{ $groupOption->name }}</option>@endforeach</select>
        <select x-model="status" x-on:change="load(1)"><option value="">All Statuses</option><option value="scheduled">Scheduled</option><option value="cancelled">Cancelled</option><option value="completed">Completed</option></select>
        <button class="btn secondary" type="submit">Apply</button><button class="btn secondary" type="button" x-show="hasFilters()" x-on:click="clearFilters()" x-cloak>Clear</button>
    </form>
    <div class="schedule-error" x-show="error" x-text="error" x-cloak></div>
<div class="table-wrap" x-bind:class="loading ? 'schedule-table-loading' : ''"><table class="table"><thead><tr><th><button class="schedule-sort" type="button" x-on:click="sortBy('exam')">Exam <span class="schedule-sort-icon" x-text="sortIcon('exam')"></span></button></th><th><button class="schedule-sort" type="button" x-on:click="sortBy('subject')">Subject <span class="schedule-sort-icon" x-text="sortIcon('subject')"></span></button></th><th><button class="schedule-sort" type="button" x-on:click="sortBy('group')">Group <span class="schedule-sort-icon" x-text="sortIcon('group')"></span></button></th><th><button class="schedule-sort" type="button" x-on:click="sortBy('start_date')">Start date <span class="schedule-sort-icon" x-text="sortIcon('start_date')"></span></button></th><th><button class="schedule-sort" type="button" x-on:click="sortBy('end_date')">End date <span class="schedule-sort-icon" x-text="sortIcon('end_date')"></span></button></th><th><button class="schedule-sort" type="button" x-on:click="sortBy('duration_minutes')">Duration <span class="schedule-sort-icon" x-text="sortIcon('duration_minutes')"></span></button></th><th><button class="schedule-sort" type="button" x-on:click="sortBy('status')">Status <span class="schedule-sort-icon" x-text="sortIcon('status')"></span></button></th><th>Actions</th></tr></thead><tbody><template x-for="schedule in rows" :key="schedule.id"><tr><td><a x-bind:href="schedule.exam_url" x-text="schedule.exam"></a></td><td x-text="schedule.subject || '—'"></td><td x-text="schedule.group || '—'"></td><td x-text="schedule.start_date"></td><td x-text="schedule.end_date || '—'"></td><td x-text="schedule.duration"></td><td><div class="admin-status-cell"><span class="badge" x-bind:class="schedule.status" x-text="schedule.status_label"></span></div></td><td><div class="admin-actions-cell"><template x-if="schedule.status === 'scheduled'"><span><a x-bind:href="schedule.edit_url">Edit</a> <form style="display:inline" method="POST" x-bind:action="schedule.cancel_url">@csrf @method('PATCH')<button class="link-button" type="submit">Cancel</button></form></span></template></div></td></tr></template><tr x-show="!loading && rows.length === 0" x-cloak><td colspan="8"><div class="admin-empty-row"><span class="admin-empty-row-icon">🔍</span>No schedules found.</div></td></tr></tbody></table></div>
    <div class="actions" style="justify-content:space-between;margin-top:18px"><span class="muted"><span x-text="meta.total ? 'Showing '+meta.from+' to '+meta.to+' of '+meta.total+' schedules' : 'No schedules found'"></span></span><div class="actions"><button class="btn secondary" type="button" x-on:click="load(meta.current_page - 1)" x-bind:disabled="loading || meta.current_page <= 1">Previous</button><span class="muted" x-text="meta.current_page+' / '+meta.last_page"></span><button class="btn secondary" type="button" x-on:click="load(meta.current_page + 1)" x-bind:disabled="loading || meta.current_page >= meta.last_page">Next</button></div></div>
</div>
<script>
    window.scheduleTable = function (endpoint, initialRows, initialMeta) {
        return {
            endpoint, rows: initialRows || [], meta: initialMeta || { current_page: 1, last_page: 1, total: 0, from: null, to: null }, search: @js($search), subjectId: @js((string) $subjectId), examId: @js((string) $examId), groupId: @js((string) $groupId), status: @js($status), sort: 'start_date', direction: 'desc', loading: false, error: '', request: null,
            hasFilters() { return this.search || this.subjectId || this.examId || this.groupId || this.status; },
            clearFilters() { this.search = ''; this.subjectId = ''; this.examId = ''; this.groupId = ''; this.status = ''; this.load(1); },
            sortIcon(field) { return this.sort === field ? (this.direction === 'asc' ? '▲' : '▼') : '↕'; },
            sortBy(field) { if (this.sort === field) this.direction = this.direction === 'asc' ? 'desc' : 'asc'; else { this.sort = field; this.direction = 'asc'; } this.load(1); },
            async load(page) {
                page = Math.max(1, page || 1);
                if (this.request) this.request.abort();
                this.request = new AbortController();
                const params = new URLSearchParams({ page, sort: this.sort, direction: this.direction });
                if (this.search) params.set('search', this.search); if (this.subjectId) params.set('course_id', this.subjectId); if (this.examId) params.set('exam_id', this.examId); if (this.groupId) params.set('group_id', this.groupId); if (this.status) params.set('status', this.status);
                this.loading = true; this.error = '';
                try { const response = await fetch(this.endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' }, signal: this.request.signal }); if (!response.ok) throw new Error('Schedule search failed'); const payload = await response.json(); this.rows = payload.data; this.meta = payload.meta; }
                catch (error) { if (error.name !== 'AbortError') this.error = 'Schedules could not be loaded. Try again.'; }
                finally { this.loading = false; }
            }
        };
    };
</script>
@endsection
