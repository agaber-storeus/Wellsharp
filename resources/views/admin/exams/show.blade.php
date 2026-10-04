@extends('layouts.admin')
@section('admin-content')
<div class="page-head hero"><div><span class="admin-kicker">{{ $course->name }}</span><h1>{{ $exam->name }}</h1><p>{{ $exam->question_order_mode->label() }} &middot; {{ $exam->status->label() }}</p></div><div class="admin-page-actions"><a class="btn secondary" href="{{ route('admin.exams.edit', $exam) }}">Edit exam</a><a class="btn" href="{{ route('admin.exam-schedules.create', ['exam_id' => $exam->id]) }}">Add group schedule</a>@if($exam->status->value === 'archived')<form method="POST" action="{{ route('admin.exams.unarchive', $exam) }}">@csrf @method('PATCH')<button class="btn secondary" type="submit">Unarchive</button></form>@else<form method="POST" action="{{ route('admin.exams.archive', $exam) }}">@csrf @method('PATCH')<button class="btn danger" type="submit">Archive</button></form>@endif</div></div>
<div class="admin-bento">
@if($exam->question_selection_mode?->value !== 'random')
<div class="admin-bento-card admin-bento-card--wide">
    <div class="admin-card-head"><span class="admin-card-icon">&#10067;</span><h3>Questions</h3><span class="badge active" style="margin-left:auto">{{ $exam->examQuestions->count() }} selected</span></div>
    <div class="exam-question-grid">
        @foreach($exam->examQuestions as $examQuestion)
            @php
                $question = $examQuestion->question;
                $answers = match ($question->type?->value) {
                    'mcq' => $question->options->map(fn ($option) => ['text' => $option->option_text, 'correct' => $option->is_correct, 'image_path' => $option->image_path])->values(),
                    'true_false' => collect([['text' => 'True', 'correct' => $question->correct_answer_boolean === true], ['text' => 'False', 'correct' => $question->correct_answer_boolean === false]]),
                    'input' => collect([['text' => $question->correct_answer_text, 'correct' => true, 'image_path' => $question->correct_answer_image_path]]),
                    default => collect(),
                };
            @endphp
            <article class="exam-question-card is-selected">
                <div class="exam-question-card-head"><span class="exam-question-selected-label">Selected &middot; Order {{ $examQuestion->display_order }}</span><div class="exam-question-badges"><span class="badge">{{ $question->code }}</span><span class="badge">{{ $question->type?->label() }}</span><span class="badge {{ $question->difficulty?->value }}">{{ $question->difficulty?->label() }}</span></div></div>
                <div class="exam-question-content">@if($question->question_image_path)<img class="exam-question-image" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($question->question_image_path) }}" alt="Question image">@endif<p class="exam-question-text">{{ $question->display_question_text }}</p></div>
                <div class="exam-question-answers" aria-label="{{ $question->type?->value === 'input' ? 'Expected answer' : 'Answer options' }}"><div class="exam-question-answers-label">{{ $question->type?->value === 'input' ? 'Expected answer' : 'Answers' }}</div>@foreach($answers as $answerIndex => $answer)<div class="exam-question-answer {{ $answer['correct'] ? 'is-correct' : '' }}"><span class="exam-question-answer-marker">{{ $answer['correct'] ? 'Correct' : chr(65 + $answerIndex) }}</span><span class="exam-question-answer-text">{{ $answer['text'] ?: 'Image answer' }}</span>@if($answer['image_path'] ?? null)<img class="exam-question-answer-image" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($answer['image_path']) }}" alt="Answer image">@endif</div>@endforeach</div>
                <div class="exam-question-card-footer"><span class="muted">{{ $course->name }}</span><a class="btn secondary small" href="{{ route('admin.courses.questions.edit', [$course, $question]) }}" target="_blank" rel="noopener">Edit Question</a></div>
            </article>
        @endforeach
    </div>
</div>
@endif
<div class="admin-bento-card admin-bento-card--wide"><div class="admin-card-head"><span class="admin-card-icon cool">&#128467;&#65039;</span><h3>Schedules</h3><a href="{{ route('admin.exam-schedules.index', ['exam_id' => $exam->id]) }}" style="margin-left:auto">View all schedules</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Group</th><th>Start date</th><th>End date</th><th>Duration</th><th>Status</th></tr></thead><tbody>@forelse($exam->schedules as $schedule)<tr><td>{{ $schedule->group?->name ?: '&mdash;' }}</td><td>{{ $schedule->start_date?->format('Y-m-d') ?: '&mdash;' }}</td><td>{{ $schedule->end_date?->format('Y-m-d') ?: '&mdash;' }}</td><td>{{ $schedule->duration_minutes }} min per student</td><td><span class="badge {{ $schedule->status->value }}">{{ $schedule->status->label() }}</span></td></tr>@empty<tr><td colspan="5"><div class="admin-empty-row"><span class="admin-empty-row-icon">&#128467;&#65039;</span>This exam has not been scheduled yet.</div></td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
