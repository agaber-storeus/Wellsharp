@extends('layouts.admin')

@section('admin-content')
<div class="page-head"><div><span class="admin-kicker">{{ $course->code }}</span><h1>Question bank</h1><p>{{ $course->name }} - {{ $questions->total() }} questions</p></div><div class="actions"><a class="btn secondary" href="{{ route('admin.courses.show', $course) }}">Subject</a><a class="btn secondary" href="{{ route('admin.courses.questions.import', $course) }}">Import Excel</a><a class="btn" href="{{ route('admin.courses.questions.create', $course) }}">Add question</a></div></div>
<div class="card">
    <form class="search" method="GET"><input name="search" value="{{ $search }}" placeholder="Search question text"><select name="difficulty"><option value="">All difficulty</option>@foreach(\App\Enums\QuestionDifficulty::cases() as $item)<option value="{{ $item->value }}" @selected($difficulty === $item->value)>{{ $item->label() }}</option>@endforeach</select><select name="type"><option value="">All types</option>@foreach(\App\Enums\QuestionType::cases() as $item)<option value="{{ $item->value }}" @selected($type === $item->value)>{{ $item->label() }}</option>@endforeach</select><button class="btn secondary">Filter</button></form>
    <div class="question-error" x-data="{ message: '' }" x-show="message" x-text="message" x-cloak></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Question</th><th>Images</th><th>Type</th><th>Difficulty</th><th>Options</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($questions as $question)
        @php($answerImages = $question->options->whereNotNull('image_path')->count() + ($question->correct_answer_image_path ? 1 : 0))
        <tr x-data="questionRow(@js(route('admin.courses.questions.destroy', [$course, $question])), @js((bool) $question->is_active))">
            <td>{{ $question->question_text }}</td>
            <td>@if($question->question_image_path)<img class="admin-question-thumb" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($question->question_image_path) }}" alt="Question image">@endif @if($answerImages)<span class="image-count">{{ $answerImages }} answer image{{ $answerImages === 1 ? '' : 's' }}</span>@endif @if(!$question->question_image_path && !$answerImages)<span class="muted">-</span>@endif</td>
            <td>{{ $question->type->label() }}</td>
            <td>{{ $question->difficulty->label() }}</td>
            <td>{{ $question->type === \App\Enums\QuestionType::Mcq ? $question->options->count() : '-' }}</td>
            <td><div class="admin-status-cell"><span class="badge" x-bind:class="active ? 'active' : 'archived'" x-text="active ? 'Active' : 'Archived'"></span></div></td>
            <td><div class="admin-actions-cell"><a href="{{ route('admin.courses.questions.edit', [$course, $question]) }}">Edit</a><template x-if="active"><button class="btn secondary small question-status-toggle" type="button" x-on:click.prevent="archive()" x-bind:disabled="archiving" x-bind:aria-busy="archiving ? 'true' : 'false'" x-text="archiving ? 'Updating...' : 'Archive'"></button></template><span class="question-row-error" x-show="error" x-text="error" x-cloak></span></div></td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">No questions found.</td></tr>
    @endforelse
    </tbody></table></div>{{ $questions->links('components.pagination') }}
</div>
<style>[x-cloak]{display:none!important}.link-button{border:0;background:none;padding:0;color:var(--admin-blue);font:inherit;cursor:pointer;margin-left:8px}.link-button:hover{text-decoration:underline}.link-button:disabled{cursor:wait;opacity:.65}.question-status-toggle{cursor:pointer!important;pointer-events:auto!important;touch-action:manipulation}.question-row-error{display:block;color:#b42318;font-size:12px;margin-top:5px}.question-error{background:#fef3f2;color:#b42318;border-radius:5px;padding:10px 12px;margin-bottom:15px}.search select{padding:10px;border:1px solid #b9c7d2;border-radius:5px;background:#fff;color:inherit;font:inherit}</style>
<script>
    window.questionRow = function (endpoint, initialActive) {
        return {
            endpoint,
            active: initialActive,
            archiving: false,
            error: '',
            async archive() {
                if (!this.active || this.archiving) return;
                this.archiving = true;
                this.error = '';
                try {
                    const response = await fetch(this.endpoint, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' } });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(payload.message || 'Question could not be archived.');
                    this.active = false;
                } catch (exception) {
                    this.error = exception.message || 'Question could not be archived.';
                } finally {
                    this.archiving = false;
                }
            }
        };
    };
</script>
@endsection
