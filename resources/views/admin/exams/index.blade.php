@extends('layouts.admin')

@section('admin-content')
<style>[x-cloak]{display:none!important}.exam-table-loading{opacity:.55;pointer-events:none}.exam-sort{border:0;background:transparent;color:inherit;font:inherit;font-weight:600;cursor:pointer;padding:0}.exam-sort:hover{color:var(--admin-blue)}.exam-sort-icon{font-size:11px;margin-left:3px}.exam-table-error{background:#fef3f2;color:#b42318;border-radius:5px;padding:10px 12px;margin-bottom:15px}.exam-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.exam-status-toggle{cursor:pointer!important;pointer-events:auto!important;touch-action:manipulation}.exam-row-error{display:block;color:#b42318;font-size:12px;margin-top:5px}.search select{padding:10px;border:1px solid #b9c7d2;border-radius:5px;background:#fff;color:inherit;font:inherit}</style>
<div class="page-head"><div><span class="admin-kicker">Subject &middot; {{ $course->code }}</span><h1>Exams</h1><p>{{ $course->name }}</p></div><div class="actions"><a class="btn secondary" href="{{ route('admin.courses.show', $course) }}">Subject</a><a class="btn" href="{{ route('admin.courses.exams.create', $course) }}">Create exam</a></div></div>
<div class="card" x-data="courseExamTable(@js(route('admin.courses.exams.data', $course)), @js($initialExams), @js(['current_page' => $exams->currentPage(), 'last_page' => $exams->lastPage(), 'total' => $exams->total(), 'from' => $exams->firstItem(), 'to' => $exams->lastItem()]))">
    <form class="search" x-on:submit.prevent="load(1)">
        <input x-model="search" x-on:input.debounce.350ms="load(1)" placeholder="Search name or code" autocomplete="off">
        <select x-model="status" x-on:change="load(1)"><option value="">All Statuses</option>@foreach(\App\Enums\ExamStatus::cases() as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</select>
        <button class="btn secondary" type="submit">Apply</button>
        <button class="btn secondary" type="button" x-show="hasFilters()" x-on:click="clearFilters()" x-cloak>Clear</button>
    </form>
    <div class="exam-table-error" x-show="error" x-text="error" x-cloak></div>
    <div class="table-wrap" x-bind:class="loading ? 'exam-table-loading' : ''">
        <table class="table">
            <thead><tr><th><button class="exam-sort" type="button" x-on:click="sortBy('name')">Name <span class="exam-sort-icon" x-text="sortIcon('name')"></span></button></th><th><button class="exam-sort" type="button" x-on:click="sortBy('code')">Code <span class="exam-sort-icon" x-text="sortIcon('code')"></span></button></th><th><button class="exam-sort" type="button" x-on:click="sortBy('questions_count')">Questions <span class="exam-sort-icon" x-text="sortIcon('questions_count')"></span></button></th><th><button class="exam-sort" type="button" x-on:click="sortBy('schedules_count')">Schedules <span class="exam-sort-icon" x-text="sortIcon('schedules_count')"></span></button></th><th>Mode</th><th><button class="exam-sort" type="button" x-on:click="sortBy('status')">Status <span class="exam-sort-icon" x-text="sortIcon('status')"></span></button></th><th></th></tr></thead>
            <tbody>
                <template x-for="exam in rows" :key="exam.id">
                    <tr>
                        <td><a x-bind:href="exam.exam_url" x-text="exam.name"></a></td>
                        <td x-text="exam.code || '-'"></td>
                        <td x-text="exam.questions_count"></td>
                        <td x-text="exam.schedules_count"></td>
                        <td x-text="exam.question_order_mode_label"></td>
                        <td><span class="badge" x-bind:class="exam.status" x-text="exam.status_label"></span></td>
                        <td><div class="exam-actions"><a x-bind:href="exam.exam_url">View</a><template x-if="exam.can_archive"><button class="btn secondary small exam-status-toggle" type="button" x-on:click.prevent="archiveExam(exam)" x-bind:disabled="exam.archiving" x-bind:aria-busy="exam.archiving ? 'true' : 'false'" x-text="exam.archiving ? 'Updating...' : 'Archive'"></button></template><template x-if="exam.can_unarchive"><button class="btn secondary small exam-status-toggle" type="button" x-on:click.prevent="unarchiveExam(exam)" x-bind:disabled="exam.archiving" x-bind:aria-busy="exam.archiving ? 'true' : 'false'" x-text="exam.archiving ? 'Updating...' : 'Unarchive'"></button></template><span class="exam-row-error" x-show="exam.rowError" x-text="exam.rowError" x-cloak></span></div></td>
                    </tr>
                </template>
                <tr x-show="!loading && rows.length === 0" x-cloak><td colspan="7"><div class="admin-empty-row"><span class="admin-empty-row-icon">🔍</span>No exams found.</div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="actions" style="justify-content:space-between;margin-top:18px"><span class="muted" x-text="meta.total ? 'Showing '+meta.from+' to '+meta.to+' of '+meta.total+' exams' : 'No exams found'"></span><div class="actions"><button class="btn secondary" type="button" x-on:click="load(meta.current_page - 1)" x-bind:disabled="loading || meta.current_page <= 1">Previous</button><span class="muted" x-text="meta.current_page+' / '+meta.last_page"></span><button class="btn secondary" type="button" x-on:click="load(meta.current_page + 1)" x-bind:disabled="loading || meta.current_page >= meta.last_page">Next</button></div></div>
</div>
<script>
    window.courseExamTable = function (endpoint, initialRows, initialMeta) {
        return {
            endpoint, rows: (initialRows || []).map(exam => ({ ...exam, archiving: false, rowError: '' })), meta: initialMeta || { current_page: 1, last_page: 1, total: 0, from: null, to: null }, search: @js($search), status: @js($status), sort: 'created_at', direction: 'desc', loading: false, error: '', request: null,
            hasFilters() { return this.search || this.status; },
            clearFilters() { this.search = ''; this.status = ''; this.load(1); },
            sortIcon(field) { return this.sort === field ? (this.direction === 'asc' ? '▲' : '▼') : '↕'; },
            sortBy(field) { if (this.sort === field) this.direction = this.direction === 'asc' ? 'desc' : 'asc'; else { this.sort = field; this.direction = 'asc'; } this.load(1); },
            async load(page) { page = Math.max(1, page || 1); if (this.request) this.request.abort(); this.request = new AbortController(); const params = new URLSearchParams({ page, sort: this.sort, direction: this.direction }); if (this.search) params.set('search', this.search); if (this.status) params.set('status', this.status); this.loading = true; this.error = ''; try { const response = await fetch(this.endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' }, signal: this.request.signal }); if (!response.ok) throw new Error('Exam search failed'); const payload = await response.json(); this.rows = (payload.data || []).map(exam => ({ ...exam, archiving: false, rowError: '' })); this.meta = payload.meta; } catch (error) { if (error.name !== 'AbortError') this.error = 'Exams could not be loaded. Try again.'; } finally { this.loading = false; } },
            async archiveExam(exam) { if (!exam.can_archive || exam.archiving) return; await this.changeArchiveState(exam, exam.archive_url, 'Exam could not be archived.', 'archived'); },
            async unarchiveExam(exam) { if (!exam.can_unarchive || exam.archiving) return; await this.changeArchiveState(exam, exam.unarchive_url, 'Exam could not be unarchived.', 'draft'); },
            async changeArchiveState(exam, url, errorMessage, state) { exam.archiving = true; exam.rowError = ''; try { const response = await fetch(url, { method: 'PATCH', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(payload.message || errorMessage); exam.status = payload.status || state; exam.status_label = payload.status_label || (state === 'draft' ? 'Draft' : 'Archived'); exam.can_archive = state !== 'archived'; exam.can_unarchive = state === 'archived'; } catch (exception) { exam.rowError = exception.message || errorMessage; } finally { exam.archiving = false; } }
        };
    };
</script>
@endsection
