@extends('layouts.admin')

@section('admin-content')
<style>
    [x-cloak] { display: none !important; }
    .exam-table-loading { opacity: .55; pointer-events: none; }
    .exam-sort { border: 0; background: transparent; color: inherit; font: inherit; font-weight: 600; cursor: pointer; padding: 0; }
    .exam-sort:hover { color: var(--admin-blue); }
    .exam-sort-icon { font-size: 11px; margin-left: 3px; }
    .exam-error { background: #fef3f2; color: #b42318; border-radius: 5px; padding: 10px 12px; margin-bottom: 15px; }
    .exam-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .exam-status-toggle { cursor: pointer !important; pointer-events: auto !important; touch-action: manipulation; }
</style>

<div class="page-head"><div><span class="admin-kicker">Exam Management</span><h1>Exams</h1><p>Search, filter, and sort reusable assessments without leaving this page.</p></div><a class="btn" href="{{ route('admin.exams.create') }}">Create exam</a></div>
<div class="card" x-data="examTable(@js(route('admin.exams.data')), @js($initialExams), @js($initialMeta))">
    <form class="search" x-on:submit.prevent="load(1)">
        <input x-model="search" x-on:input.debounce.350ms="load(1)" placeholder="Search exam name, code, or Subject" autocomplete="off">
        <select x-model="courseId" x-on:change="load(1)"><option value="">All Subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select>
        <select x-model="status" x-on:change="load(1)"><option value="">All Statuses</option><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select>
        <button class="btn secondary" type="submit">Apply</button><button class="btn secondary" type="button" x-show="hasFilters()" x-on:click="clearFilters()" x-cloak>Clear</button>
    </form>
    <div class="exam-error" x-show="error" x-text="error" x-cloak></div>
    <div class="table-wrap" x-bind:class="loading ? 'exam-table-loading' : ''"><table class="table"><thead><tr><th><button class="exam-sort" type="button" x-on:click="sortBy('name')">Exam <span class="exam-sort-icon" x-text="sortIcon('name')"></span></button></th><th><button class="exam-sort" type="button" x-on:click="sortBy('subject')">Subject <span class="exam-sort-icon" x-text="sortIcon('subject')"></span></button></th><th><button class="exam-sort" type="button" x-on:click="sortBy('questions_count')">Questions <span class="exam-sort-icon" x-text="sortIcon('questions_count')"></span></button></th><th><button class="exam-sort" type="button" x-on:click="sortBy('schedules_count')">Schedules <span class="exam-sort-icon" x-text="sortIcon('schedules_count')"></span></button></th><th><button class="exam-sort" type="button" x-on:click="sortBy('status')">Status <span class="exam-sort-icon" x-text="sortIcon('status')"></span></button></th><th></th></tr></thead><tbody>
    <template x-for="exam in rows" :key="exam.id"><tr><td><a x-bind:href="exam.exam_url" x-text="exam.name"></a><br><small class="muted" x-text="exam.code || 'No code'"></small></td><td x-text="exam.subject || '&mdash;'"></td><td x-text="exam.questions_count"></td><td x-text="exam.schedules_count"></td><td><span class="badge" x-bind:class="exam.status" x-text="exam.status_label"></span></td><td><div class="exam-actions"><a x-bind:href="exam.exam_url">View</a> <template x-if="exam.can_archive"><button class="btn secondary small exam-status-toggle" type="button" x-on:click.prevent="archiveExam(exam)" x-bind:disabled="exam.archiving" x-bind:aria-busy="exam.archiving ? 'true' : 'false'" x-text="exam.archiving ? 'Updating...' : 'Archive'"></button></template><template x-if="exam.status === 'published'"><a x-bind:href="exam.schedule_url">Schedule</a></template></div></td></tr></template>
    <tr x-show="!loading && rows.length === 0" x-cloak><td colspan="6" class="muted">No exams found.</td></tr></tbody></table></div>
    <div class="actions" style="justify-content:space-between;margin-top:18px"><span class="muted"><span x-text="meta.total ? 'Showing '+meta.from+' to '+meta.to+' of '+meta.total+' exams' : 'No exams found'"></span></span><div class="actions"><button class="btn secondary" type="button" x-on:click="load(meta.current_page - 1)" x-bind:disabled="loading || meta.current_page <= 1">Previous</button><span class="muted" x-text="meta.current_page+' / '+meta.last_page"></span><button class="btn secondary" type="button" x-on:click="load(meta.current_page + 1)" x-bind:disabled="loading || meta.current_page >= meta.last_page">Next</button></div></div>
</div>
<script>
    window.examTable = function (endpoint, initialRows, initialMeta) {
        return {
            endpoint, rows: initialRows || [], meta: initialMeta || { current_page: 1, last_page: 1, total: 0, from: null, to: null }, search: @js($search), courseId: @js((string) $courseId), status: @js($status), sort: 'created_at', direction: 'desc', loading: false, error: '', request: null,
            hasFilters() { return this.search || this.courseId || this.status; },
            clearFilters() { this.search = ''; this.courseId = ''; this.status = ''; this.load(1); },
            sortIcon(field) { return this.sort === field ? (this.direction === 'asc' ? '▲' : '▼') : '↕'; },
            sortBy(field) { if (this.sort === field) this.direction = this.direction === 'asc' ? 'desc' : 'asc'; else { this.sort = field; this.direction = 'asc'; } this.load(1); },
            async load(page) { page = Math.max(1, page || 1); if (this.request) this.request.abort(); this.request = new AbortController(); const params = new URLSearchParams({ page, sort: this.sort, direction: this.direction }); if (this.search) params.set('search', this.search); if (this.courseId) params.set('course_id', this.courseId); if (this.status) params.set('status', this.status); this.loading = true; this.error = ''; try { const response = await fetch(this.endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' }, signal: this.request.signal }); if (!response.ok) throw new Error('Exam search failed'); const payload = await response.json(); this.rows = payload.data; this.meta = payload.meta; } catch (error) { if (error.name !== 'AbortError') this.error = 'Exams could not be loaded. Try again.'; } finally { this.loading = false; } },
            async archiveExam(exam) { if (!exam.can_archive || exam.archiving) return; exam.archiving = true; this.error = ''; try { const response = await fetch(exam.archive_url, { method: 'PATCH', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(payload.message || 'Exam could not be archived.'); exam.status = payload.status; exam.status_label = payload.status_label; exam.can_archive = false; } catch (exception) { this.error = exception.message || 'Exam could not be archived.'; } finally { exam.archiving = false; } }
        };
    };
</script>
@endsection
