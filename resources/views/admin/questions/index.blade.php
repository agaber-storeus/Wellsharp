@extends('layouts.admin')
@section('admin-content')
<div class="page-head"><div><span class="admin-kicker">{{ $course->code }}</span><h1>Question bank</h1><p>{{ $course->name }} - {{ $questions->total() }} questions</p></div><div class="actions"><a class="btn secondary" href="{{ route('admin.courses.show', $course) }}">Subject</a><a class="btn secondary" href="{{ route('admin.courses.questions.import', $course) }}">Import Excel</a><a class="btn" href="{{ route('admin.courses.questions.create', $course) }}">Add question</a></div></div>
<div class="card">
    <form class="search" method="GET"><input name="search" value="{{ $search }}" placeholder="Search question text"><select name="difficulty"><option value="">All difficulty</option>@foreach(\App\Enums\QuestionDifficulty::cases() as $item)<option value="{{ $item->value }}" @selected($difficulty === $item->value)>{{ $item->label() }}</option>@endforeach</select><select name="type"><option value="">All types</option>@foreach(\App\Enums\QuestionType::cases() as $item)<option value="{{ $item->value }}" @selected($type === $item->value)>{{ $item->label() }}</option>@endforeach</select><button class="btn secondary">Filter</button></form>
    <div class="table-wrap"><table class="table"><thead><tr><th>Question</th><th>Images</th><th>Type</th><th>Difficulty</th><th>Options</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($questions as $question)
        @php($answerImages = $question->options->whereNotNull('image_path')->count() + ($question->correct_answer_image_path ? 1 : 0))
        <tr><td>{{ $question->question_text }}</td><td>@if($question->question_image_path)<img class="admin-question-thumb" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($question->question_image_path) }}" alt="Question image">@endif @if($answerImages)<span class="image-count">{{ $answerImages }} answer image{{ $answerImages === 1 ? '' : 's' }}</span>@endif @if(!$question->question_image_path && !$answerImages)<span class="muted">-</span>@endif</td><td>{{ $question->type->label() }}</td><td>{{ $question->difficulty->label() }}</td><td>{{ $question->type === \App\Enums\QuestionType::Mcq ? $question->options->count() : '-' }}</td><td><span class="badge {{ $question->is_active ? 'active' : 'archived' }}">{{ $question->is_active ? 'Active' : 'Archived' }}</span></td><td><a href="{{ route('admin.courses.questions.edit', [$course, $question]) }}">Edit</a>@if($question->is_active) <form style="display:inline" method="POST" action="{{ route('admin.courses.questions.destroy', [$course, $question]) }}">@csrf @method('DELETE')<button class="link-button" type="submit">Archive</button></form>@endif</td></tr>
    @empty
        <tr><td colspan="7" class="muted">No questions found.</td></tr>
    @endforelse
    </tbody></table></div>{{ $questions->links('components.pagination') }}
</div>
<style>.link-button{border:0;background:none;padding:0;color:var(--admin-blue);font:inherit;cursor:pointer;margin-left:8px}.link-button:hover{text-decoration:underline}.search select{padding:10px;border:1px solid #b9c7d2;border-radius:5px;background:#fff;color:inherit;font:inherit}</style>
@endsection
