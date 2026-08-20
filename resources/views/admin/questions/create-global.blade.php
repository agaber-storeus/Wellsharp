@extends('layouts.admin')

@section('admin-content')
<div class="question-creation-page">
    <div class="page-head admin-page-head hero">
        <div>
            <span class="admin-kicker">Question Bank</span>
            <div class="question-breadcrumb"><a href="{{ route('admin.questions.index') }}">Questions</a><span aria-hidden="true">›</span><span>Create</span></div>
            <h1>Create question</h1>
            <p>Create a reusable question and assign it to a Subject.</p>
        </div>
        <a class="btn secondary" href="{{ route('admin.questions.index') }}">Back to questions</a>
    </div>

    <form id="globalQuestionForm" method="POST" action="#" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="creation_source" value="global">
        @include('admin.questions._creation-wizard', ['isGlobal' => true, 'cancelUrl' => route('admin.questions.index')])
    </form>
</div>
<script>
(() => { const form = document.getElementById('globalQuestionForm'); const urls = @js($storeUrls); const sync = () => { const subjectId = form.querySelector('[name="course_id"]')?.value; form.action = urls[subjectId] || '#'; }; form.addEventListener('change', sync); form.addEventListener('submit', event => { const subjectId = form.querySelector('[name="course_id"]')?.value; if (!urls[subjectId]) { event.preventDefault(); document.getElementById('wizard_subject')?.focus(); } }); sync(); })();
</script>
@endsection
