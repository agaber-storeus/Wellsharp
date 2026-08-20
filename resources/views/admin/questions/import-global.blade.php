@extends('layouts.admin')
@section('admin-content')
<div class="page-head admin-page-head hero"><div><span class="admin-kicker">Exam Management</span><h1>Import Questions</h1><p>Download the Excel template, fill in many Questions, select their Subject Question Bank, and validate the upload.</p></div><a class="btn secondary" href="{{ route('admin.questions.index') }}">Back to Questions</a></div>
<div class="card"><h2>Upload Question spreadsheet</h2><p class="admin-intro">The import is previewed before anything is saved. Any invalid row blocks the complete import.</p><p class="muted">Spreadsheet imports cover question text and answer choices. Add optional question or answer images afterward from the question editor.</p><form id="globalQuestionImportForm" method="POST" action="#" enctype="multipart/form-data">@csrf
    <div class="field" style="max-width:560px"><x-admin.label for="import_question_course" required>Subject question bank</x-admin.label><select id="import_question_course" name="course_id" required><option value="">Select Subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected($selectedCourseId === (string) $subject->id)>{{ $subject->code }} — {{ $subject->name }}</option>@endforeach</select></div>
    <div class="field" style="margin-top:18px"><x-admin.label for="questions_file" required>Excel file</x-admin.label><input id="questions_file" type="file" name="questions_file" accept=".xlsx,.xls,.csv" required><small class="muted">Use only the downloaded template columns.</small></div>
    <div class="actions" style="margin-top:20px"><a id="downloadQuestionTemplate" class="btn secondary" href="#" aria-disabled="true">Download Excel template</a><button class="btn" type="submit">Validate and preview</button></div>
</form></div>
<script>
(() => {
    const form = document.getElementById('globalQuestionImportForm');
    const subject = document.getElementById('import_question_course');
    const download = document.getElementById('downloadQuestionTemplate');
    const templateUrls = @js($templateUrls);
    const previewUrls = @js($previewUrls);
    const error = document.createElement('p');
    error.className = 'alert error';
    error.hidden = true;
    error.textContent = 'Select a Subject before downloading or uploading the Question file.';
    form.prepend(error);
    const sync = () => {
        const templateUrl = templateUrls[subject.value];
        download.href = templateUrl || '#';
        download.setAttribute('aria-disabled', templateUrl ? 'false' : 'true');
        form.action = previewUrls[subject.value] || '#';
    };
    subject.addEventListener('change', sync);
    download.addEventListener('click', event => { if (!templateUrls[subject.value]) { event.preventDefault(); error.hidden = false; subject.focus(); } });
    form.addEventListener('submit', event => { if (!previewUrls[subject.value]) { event.preventDefault(); error.hidden = false; subject.focus(); } });
    sync();
})();
</script>
@endsection
