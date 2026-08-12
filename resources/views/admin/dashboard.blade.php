@extends('layouts.admin')

@section('admin-content')
<div class="page-head dashboard-hero">
    <div>
        <span class="admin-kicker">System overview</span>
        <h1>Admin dashboard</h1>
        <p>Manage the WellSharp platform and prepare the data used by operational teams.</p>
    </div>
    <a class="btn" href="{{ route('admin.users.create') }}">Create user</a>
</div>

<section class="stats" aria-label="Platform statistics">
    <div class="card stat"><span class="muted">All users</span><strong>{{ $userCount }}</strong><span class="stat-caption">Registered accounts</span></div>
    <div class="card stat"><span class="muted">Active users</span><strong>{{ $activeUserCount }}</strong><span class="stat-caption">Ready to sign in</span></div>
    <div class="card stat"><span class="muted">Active providers</span><strong>{{ $providerCount }}</strong><span class="stat-caption">Training locations</span></div>
    <div class="card stat"><span class="muted">Active subjects</span><strong>{{ $courseCount }}</strong><span class="stat-caption">Configured subjects</span></div>
    <div class="card stat"><span class="muted">Issued certificates</span><strong>{{ $certificateCount }}</strong><a class="stat-link" href="{{ route('admin.certificates.index') }}">View certificates <span aria-hidden="true">&rarr;</span></a></div>
</section>

<section class="card setup-card" aria-labelledby="setup-title">
    <div class="admin-section-head"><div><span class="admin-kicker">Workspace foundations</span><h2 id="setup-title">Continue setup</h2></div><span class="badge active">Admin only</span></div>
    <p class="admin-intro">Create users, providers, Subject reference values, Subjects, and Classes/Exams from the management sections. Any active Proctor or Instructor can control any Class with their own exam-control ID.</p>
    <div class="actions"><a class="btn secondary" href="{{ route('admin.providers.index') }}">Manage providers</a><a class="btn secondary" href="{{ route('admin.courses.index') }}">Review Subjects</a><a class="btn secondary" href="{{ route('admin.classes.index') }}">Manage classes</a></div>
</section>

<section class="survey-dashboard" aria-labelledby="survey-dashboard-title">
    <div class="survey-dashboard-heading">
        <div>
            <span class="admin-kicker">Database-backed responses</span>
            <h2 id="survey-dashboard-title">Student Survey Answers</h2>
            <p>Completed survey answers are loaded from the student survey records. Student identity is intentionally not displayed.</p>
        </div>
        <div class="survey-dashboard-count"><strong>{{ $completedSurveyCount }}</strong><span>completed</span></div>
    </div>

    @if($surveyResponses->isEmpty())
        <div class="survey-dashboard-empty">No completed student surveys are available yet.</div>
    @else
        <div class="survey-response-grid">
            @foreach($surveyResponses as $survey)
                <article class="survey-response-card" x-data="{ open: true }">
                    <button class="survey-response-toggle" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()">
                        <span><span class="survey-response-kicker">Anonymous response</span><strong>#{{ str($survey->public_id)->substr(0, 8)->upper() }}</strong></span>
                        <span class="survey-response-date">{{ $survey->completed_at?->format('M j, Y · g:i A') ?: 'Completed' }} <span class="survey-response-chevron" x-text="open ? '−' : '+'"></span></span>
                    </button>
                    <div class="survey-response-meta">
                        <span>{{ $survey->schedule?->trainingClass?->class_number ?: 'Class not assigned' }}</span>
                        <span>{{ $survey->schedule?->exam?->name ?: 'Exam not assigned' }}</span>
                        <span>{{ $survey->schedule?->exam?->subject?->name ?: 'Subject not assigned' }}</span>
                    </div>
                    <dl class="survey-answer-list" x-show="open" x-transition>
                        @forelse($survey->answers as $answer)
                            <div class="survey-answer-row"><dt>{{ $surveyQuestionLabels[$answer->question_key] ?? $answer->question_key }}</dt><dd>{{ $answer->answer !== null && $answer->answer !== '' ? $answer->answer : 'No answer provided' }}</dd></div>
                        @empty
                            <div class="survey-answer-empty">This completed survey has no stored answers.</div>
                        @endforelse
                    </dl>
                </article>
            @endforeach
        </div>
        @if($completedSurveyCount > $surveyResponses->count())<p class="survey-dashboard-note">Showing the {{ $surveyResponses->count() }} most recent completed responses.</p>@endif
    @endif
</section>
@endsection
