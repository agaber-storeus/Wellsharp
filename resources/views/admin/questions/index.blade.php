@extends('layouts.admin')

@section('admin-content')
<style>[x-cloak]{display:none!important}.question-table-loading{opacity:.55;pointer-events:none}.question-sort{border:0;background:transparent;color:inherit;font:inherit;font-weight:600;cursor:pointer;padding:0}.question-sort:hover{color:var(--admin-blue)}.question-sort-icon{font-size:11px;margin-left:3px}.question-error{background:#fef3f2;color:#b42318;border-radius:5px;padding:10px 12px;margin-bottom:15px}.question-status-toggle{cursor:pointer!important;pointer-events:auto!important;touch-action:manipulation}.search select{padding:10px;border:1px solid #b9c7d2;border-radius:5px;background:#fff;color:inherit;font:inherit}</style>
<div class="page-head"><div><span class="admin-kicker">{{ $course->code }}</span><h1>Question bank</h1><p>{{ $course->name }} - {{ $questions->total() }} questions</p></div><div class="actions"><a class="btn secondary" href="{{ route('admin.courses.show', $course) }}">Subject</a><a class="btn secondary" href="{{ route('admin.courses.questions.import', $course) }}">Import Excel</a><a class="btn" href="{{ route('admin.courses.questions.create', $course) }}">Add question</a></div></div>
<div class="card" x-data="courseQuestionTable(@js(route('admin.courses.questions.data', $course)), @js($initialQuestions), @js(['current_page' => $questions->currentPage(), 'last_page' => $questions->lastPage(), 'total' => $questions->total(), 'from' => $questions->firstItem(), 'to' => $questions->lastItem()]))">
    <form class="search" x-on:submit.prevent="load(1)">
        <input x-model="search" x-on:input.debounce.350ms="load(1)" placeholder="Search question text" autocomplete="off">
        <select x-model="difficulty" x-on:change="load(1)"><option value="">All difficulty</option>@foreach(\App\Enums\QuestionDifficulty::cases() as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</select>
        <select x-model="type" x-on:change="load(1)"><option value="">All types</option>@foreach(\App\Enums\QuestionType::cases() as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</select>
        <select x-model="status" x-on:change="load(1)"><option value="">All Statuses</option><option value="active">Active</option><option value="archived">Archived</option></select>
        <button class="btn secondary" type="submit">Filter</button>
        <button class="btn secondary" type="button" x-show="hasFilters()" x-on:click="clearFilters()" x-cloak>Clear</button>
    </form>
    <div class="question-error" x-show="error" x-text="error" x-cloak></div>
    <div class="table-wrap" x-bind:class="loading ? 'question-table-loading' : ''">
        <table class="table">
            <thead><tr><th>Code</th><th><button class="question-sort" type="button" x-on:click="sortBy('question_text')">Question <span class="question-sort-icon" x-text="sortIcon('question_text')"></span></button></th><th>Images</th><th><button class="question-sort" type="button" x-on:click="sortBy('type')">Type <span class="question-sort-icon" x-text="sortIcon('type')"></span></button></th><th><button class="question-sort" type="button" x-on:click="sortBy('difficulty')">Difficulty <span class="question-sort-icon" x-text="sortIcon('difficulty')"></span></button></th><th>Options</th><th><button class="question-sort" type="button" x-on:click="sortBy('is_active')">Status <span class="question-sort-icon" x-text="sortIcon('is_active')"></span></button></th><th>Actions</th></tr></thead>
            <tbody>
                <template x-for="question in rows" :key="question.id">
                    <tr>
                        <td x-text="question.code"></td>
                        <td x-text="question.question_text"></td>
                        <td><template x-if="question.question_image_url"><img class="admin-question-thumb" x-bind:src="question.question_image_url" alt="Question image"></template><span class="image-count" x-show="question.answer_images_count" x-text="question.answer_images_count + ' answer image' + (question.answer_images_count === 1 ? '' : 's')"></span><span class="muted" x-show="!question.question_image_url && !question.answer_images_count">&mdash;</span></td>
                        <td x-text="question.type_label"></td>
                        <td x-text="question.difficulty_label"></td>
                        <td x-text="question.options_count ?? '-'"></td>
                        <td><div class="admin-status-cell"><span class="badge" x-bind:class="question.active ? 'active' : 'archived'" x-text="question.active ? 'Active' : 'Archived'"></span></div></td>
                        <td><div class="admin-actions-cell"><a x-bind:href="question.edit_url">Edit</a><template x-if="question.active"><button class="btn secondary small question-status-toggle" type="button" x-on:click.prevent="archiveQuestion(question)" x-bind:disabled="question.archiving" x-bind:aria-busy="question.archiving ? 'true' : 'false'" x-text="question.archiving ? 'Updating...' : 'Archive'"></button></template><span class="question-row-error" x-show="question.rowError" x-text="question.rowError" x-cloak></span></div></td>
                    </tr>
                </template>
                <tr x-show="!loading && rows.length === 0" x-cloak><td colspan="8"><div class="admin-empty-row"><span class="admin-empty-row-icon">🔍</span>No questions found.</div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="actions" style="justify-content:space-between;margin-top:18px"><span class="muted" x-text="meta.total ? 'Showing '+meta.from+' to '+meta.to+' of '+meta.total+' questions' : 'No questions found'"></span><div class="actions"><button class="btn secondary" type="button" x-on:click="load(meta.current_page - 1)" x-bind:disabled="loading || meta.current_page <= 1">Previous</button><span class="muted" x-text="meta.current_page+' / '+meta.last_page"></span><button class="btn secondary" type="button" x-on:click="load(meta.current_page + 1)" x-bind:disabled="loading || meta.current_page >= meta.last_page">Next</button></div></div>
</div>
<script>
    window.courseQuestionTable = function (endpoint, initialRows, initialMeta) {
        return {
            endpoint, rows: initialRows || [], meta: initialMeta || { current_page: 1, last_page: 1, total: 0, from: null, to: null }, search: @js($search), difficulty: @js($difficulty), type: @js($type), status: @js($status), sort: 'created_at', direction: 'desc', loading: false, error: '', request: null,
            hasFilters() { return this.search || this.difficulty || this.type || this.status; },
            clearFilters() { this.search = ''; this.difficulty = ''; this.type = ''; this.status = ''; this.load(1); },
            sortIcon(field) { return this.sort === field ? (this.direction === 'asc' ? '▲' : '▼') : '↕'; },
            sortBy(field) { if (this.sort === field) this.direction = this.direction === 'asc' ? 'desc' : 'asc'; else { this.sort = field; this.direction = 'asc'; } this.load(1); },
            async load(page) { page = Math.max(1, page || 1); if (this.request) this.request.abort(); this.request = new AbortController(); const params = new URLSearchParams({ page, sort: this.sort, direction: this.direction }); if (this.search) params.set('search', this.search); if (this.difficulty) params.set('difficulty', this.difficulty); if (this.type) params.set('type', this.type); if (this.status) params.set('status', this.status); this.loading = true; this.error = ''; try { const response = await fetch(this.endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' }, signal: this.request.signal }); if (!response.ok) throw new Error('Question search failed'); const payload = await response.json(); this.rows = payload.data; this.meta = payload.meta; } catch (error) { if (error.name !== 'AbortError') this.error = 'Questions could not be loaded. Try again.'; } finally { this.loading = false; } },
            async archiveQuestion(question) { if (!question.active || question.archiving) return; question.archiving = true; question.rowError = ''; try { const response = await fetch(question.archive_url, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(payload.message || 'Question could not be archived.'); question.active = false; } catch (exception) { question.rowError = exception.message || 'Question could not be archived.'; } finally { question.archiving = false; } }
        };
    };
</script>
@endsection
