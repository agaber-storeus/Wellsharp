@extends('layouts.admin')

@section('admin-content')
<div class="question-creation-page">
    <div class="page-head admin-page-head hero">
        <div>
            <span class="admin-kicker">{{ $course->code }}</span>
            <div class="question-breadcrumb"><a href="{{ route('admin.courses.questions.index', $course) }}">Questions</a><span aria-hidden="true">›</span><span>Create</span></div>
            <h1>Create question</h1>
            <p>{{ $course->name }} · Build a question for this Subject.</p>
        </div>
        <a class="btn secondary" href="{{ route('admin.courses.questions.index', $course) }}">Back to question bank</a>
    </div>

    <form method="POST" action="{{ route('admin.courses.questions.store', $course) }}" enctype="multipart/form-data">
        @csrf
        @include('admin.questions._creation-wizard', ['isGlobal' => false, 'cancelUrl' => route('admin.courses.questions.index', $course)])
    </form>
</div>
@endsection
