@extends('layouts.admin')

@section('admin-content')
<div class="question-creation-page">
    <div class="page-head admin-page-head hero">
        <div>
            <span class="admin-kicker">{{ $course->code }}</span>
            <div class="question-breadcrumb"><a href="{{ route('admin.courses.questions.index', $course) }}">Questions</a><span aria-hidden="true">›</span><span>Edit</span></div>
            <h1>Edit question</h1>
            <p>{{ $course->name }} · Update the question details and answer.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn secondary" href="{{ route('admin.courses.questions.index', $course) }}">Back to question bank</a>
            @if($question->is_active)
                <form method="POST" action="{{ route('admin.courses.questions.destroy', [$course, $question]) }}">
                    @csrf @method('DELETE')
                    <button class="btn danger" type="submit">Archive</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.courses.questions.unarchive', [$course, $question]) }}">
                    @csrf @method('PATCH')
                    <button class="btn secondary" type="submit">Unarchive</button>
                </form>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.courses.questions.update', [$course, $question]) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.questions._creation-wizard', ['isGlobal' => false, 'cancelUrl' => route('admin.courses.questions.index', $course)])
    </form>
</div>
@endsection
