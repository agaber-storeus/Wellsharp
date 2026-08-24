@extends('layouts.admin')

@section('admin-content')
<div class="page-head hero dashboard-hero">
    <div>
        <span class="admin-kicker">System overview</span>
        <h1>Admin dashboard</h1>
        <p>A real-time summary of Classes, Students, Exams, Certificates, and Staff across the whole platform.</p>
    </div>
    <a class="btn" href="{{ route('admin.users.create') }}">Create user</a>
</div>

{{-- Top-level KPIs --}}
<section class="dash-kpi-grid" aria-label="Key metrics">
    @foreach($dashboard['kpis'] as $kpi)
        <div class="card stat">
            <span class="muted">{{ $kpi['label'] }}</span>
            <strong>{{ is_numeric($kpi['value']) ? number_format($kpi['value']) : $kpi['value'] }}</strong>
            <span class="stat-caption">{{ $kpi['caption'] }}</span>
            @isset($kpi['url'])<a class="stat-link" href="{{ $kpi['url'] }}">View <span aria-hidden="true">&rarr;</span></a>@endisset
        </div>
    @endforeach
</section>

<section class="card setup-card dash-section" aria-labelledby="setup-title">
    <div class="admin-section-head"><div><span class="admin-kicker">Workspace foundations</span><h2 id="setup-title">Continue setup</h2></div><span class="badge active">Admin only</span></div>
    <p class="admin-intro">Create users, providers, Subject reference values, Subjects, and Classes/Exams from the management sections.</p>
    <div class="actions"><a class="btn secondary" href="{{ route('admin.providers.index') }}">Manage providers</a><a class="btn secondary" href="{{ route('admin.courses.index') }}">Review Subjects</a><a class="btn secondary" href="{{ route('admin.classes.index') }}">Manage classes</a></div>
</section>

{{-- Class lifecycle overview --}}
<section class="card dash-section" aria-labelledby="class-lifecycle-title">
    <div class="admin-section-head"><div><span class="admin-kicker">Operational status</span><h2 id="class-lifecycle-title">Class lifecycle</h2></div><span class="muted">{{ number_format($dashboard['class_status']['total']) }} total</span></div>

    <div class="dash-status-bar" role="img" aria-label="Classes by status">
        @foreach($dashboard['class_status']['breakdown'] as $segment)
            @if($segment['percent'] > 0)
                <div class="dash-status-seg" data-status="{{ $segment['status'] }}" style="width:{{ $segment['percent'] }}%" title="{{ $segment['label'] }}: {{ $segment['count'] }} ({{ $segment['percent'] }}%)"></div>
            @endif
        @endforeach
    </div>
    <div class="dash-status-legend">
        @foreach($dashboard['class_status']['breakdown'] as $segment)
            <span class="dash-status-legend-item"><span class="dash-status-legend-dot" data-status="{{ $segment['status'] }}"></span>{{ $segment['label'] }} <strong>{{ number_format($segment['count']) }}</strong> <span class="muted">({{ $segment['percent'] }}%)</span></span>
        @endforeach
    </div>

    @if($dashboard['class_status']['legacy_unassigned'] > 0)
        <p class="dash-note">{{ number_format($dashboard['class_status']['legacy_unassigned']) }} legacy Class(es) are missing a Proctor or Instructor assignment — see Attention below.</p>
    @endif
</section>

{{-- Student / enrollment overview --}}
<section class="card dash-section" aria-labelledby="enrollment-title">
    <div class="admin-section-head"><div><span class="admin-kicker">Students</span><h2 id="enrollment-title">Student &amp; enrollment overview</h2></div></div>
    <div class="dash-mini-stats">
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['enrollment']['unique_students']) }}</strong><span>Unique students</span></div>
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['enrollment']['total']) }}</strong><span>Enrollments</span></div>
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['enrollment']['by_status']['enrolled'] ?? 0) }}</strong><span>Enrolled</span></div>
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['enrollment']['by_status']['completed'] ?? 0) }}</strong><span>Completed</span></div>
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['enrollment']['by_status']['withdrawn'] ?? 0) }}</strong><span>Withdrawn</span></div>
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['enrollment']['students_with_attempts']) }}</strong><span>Students with an attempt</span></div>
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['enrollment']['students_without_attempts']) }}</strong><span>Students without an attempt</span></div>
    </div>
    <p class="dash-note">"Unique students" counts distinct students; "Enrollments" counts every Class enrollment (a student enrolled in 3 Classes counts once and three times, respectively).</p>
</section>

{{-- Exam performance + Skills Score overrides --}}
<div class="dash-cols-2 dash-section">
    <section class="card" aria-labelledby="exam-performance-title">
        <div class="admin-section-head"><div><span class="admin-kicker">Effective score</span><h2 id="exam-performance-title">Exam performance</h2></div></div>

        @php($scored = $dashboard['exam_performance']['scored_total'])
        <div class="dash-bar-row">
            <span class="dash-bar-label">Passed</span>
            <div class="dash-bar-track"><div class="dash-bar-fill pass" style="width:{{ $scored > 0 ? round($dashboard['exam_performance']['passed'] / $scored * 100, 1) : 0 }}%"></div></div>
            <span class="dash-bar-value">{{ number_format($dashboard['exam_performance']['passed']) }}</span>
        </div>
        <div class="dash-bar-row">
            <span class="dash-bar-label">Failed</span>
            <div class="dash-bar-track"><div class="dash-bar-fill fail" style="width:{{ $scored > 0 ? round($dashboard['exam_performance']['failed'] / $scored * 100, 1) : 0 }}%"></div></div>
            <span class="dash-bar-value">{{ number_format($dashboard['exam_performance']['failed']) }}</span>
        </div>

        <div class="dash-mini-stats">
            <div class="dash-mini-stat"><strong>{{ $dashboard['exam_performance']['pass_rate'] !== null ? number_format($dashboard['exam_performance']['pass_rate'], 1).'%' : '—' }}</strong><span>Pass rate</span></div>
            <div class="dash-mini-stat"><strong>{{ $dashboard['exam_performance']['average_effective_score'] !== null ? number_format($dashboard['exam_performance']['average_effective_score'], 1) : '—' }}</strong><span>Average effective score</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['exam_performance']['attempts_completed']) }}</strong><span>Attempts completed</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['exam_performance']['attempts_pending']) }}</strong><span>Pending / in progress</span></div>
        </div>
        <p class="dash-note">Pass/fail uses the effective score — the Skills Score override when set, otherwise the raw Knowledge Exam score — over the {{ number_format($scored) }} attempts that have been scored.</p>
    </section>

    <section class="card" aria-labelledby="skills-overrides-title">
        <div class="admin-section-head"><div><span class="admin-kicker">Manual overrides</span><h2 id="skills-overrides-title">Skills Score overrides</h2></div></div>
        <div class="dash-mini-stats">
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['skills_overrides']['active']) }}</strong><span>Active overrides</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['skills_overrides']['fail_to_pass']) }}</strong><span>Changed Fail &rarr; Pass</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['skills_overrides']['pass_to_fail']) }}</strong><span>Changed Pass &rarr; Fail</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['skills_overrides']['no_change']) }}</strong><span>No change to outcome</span></div>
        </div>
        @if($dashboard['skills_overrides']['active'] === 0)
            <p class="dash-note">No enrollments currently have a Skills Score override.</p>
        @elseif($dashboard['skills_overrides']['not_yet_scored'] > 0)
            <p class="dash-note">{{ number_format($dashboard['skills_overrides']['not_yet_scored']) }} override(s) belong to a student with no scored Knowledge Exam attempt yet, so no change-in-outcome could be determined.</p>
        @endif
    </section>
</div>

{{-- Certificates --}}
<section class="card dash-section" aria-labelledby="certificates-title">
    <div class="admin-section-head"><div><span class="admin-kicker">Credentials</span><h2 id="certificates-title">Certificates</h2></div><a class="stat-link" href="{{ route('admin.certificates.index') }}">View certificates <span aria-hidden="true">&rarr;</span></a></div>
    <div class="dash-mini-stats">
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['certificates']['issued']) }}</strong><span>Historical certificates issued</span></div>
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['certificates']['currently_valid']) }}</strong><span>Currently valid</span></div>
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['certificates']['expiring_soon']) }}</strong><span>Expiring within 30 days</span></div>
        <div class="dash-mini-stat"><strong>{{ number_format($dashboard['certificates']['expired']) }}</strong><span>Expired</span></div>
        @if($dashboard['certificates']['revoked'] > 0)
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['certificates']['revoked']) }}</strong><span>Revoked</span></div>
        @endif
    </div>
    <p class="dash-note">A certificate remains on record once issued even if a later Skills Score change would now fail the student — "currently valid" reflects expiration only, not current eligibility.</p>
</section>

{{-- Staff overview --}}
<div class="dash-cols-2 dash-section">
    <section class="card" aria-labelledby="proctor-workload-title">
        <div class="admin-section-head"><div><span class="admin-kicker">{{ number_format($dashboard['staff']['active_proctors']) }} active</span><h2 id="proctor-workload-title">Proctor workload</h2></div><a class="stat-link" href="{{ route('admin.users.index') }}">Manage staff <span aria-hidden="true">&rarr;</span></a></div>
        @if(count($dashboard['staff']['proctor_workload']))
            <ul class="dash-workload-list">
                @foreach($dashboard['staff']['proctor_workload'] as $row)
                    <li><span>{{ $row['name'] }}</span><strong>{{ number_format($row['count']) }} class{{ $row['count'] === 1 ? '' : 'es' }}</strong></li>
                @endforeach
            </ul>
        @else
            <p class="dash-workload-empty">No active Proctors are currently assigned to a planned or active Class.</p>
        @endif
    </section>

    <section class="card" aria-labelledby="instructor-workload-title">
        <div class="admin-section-head"><div><span class="admin-kicker">{{ number_format($dashboard['staff']['active_instructors']) }} active</span><h2 id="instructor-workload-title">Instructor workload</h2></div><a class="stat-link" href="{{ route('admin.users.index') }}">Manage staff <span aria-hidden="true">&rarr;</span></a></div>
        @if(count($dashboard['staff']['instructor_workload']))
            <ul class="dash-workload-list">
                @foreach($dashboard['staff']['instructor_workload'] as $row)
                    <li><span>{{ $row['name'] }}</span><strong>{{ number_format($row['count']) }} class{{ $row['count'] === 1 ? '' : 'es' }}</strong></li>
                @endforeach
            </ul>
        @else
            <p class="dash-workload-empty">No active Instructors are currently assigned to a planned or active Class.</p>
        @endif
    </section>
</div>

{{-- Platform modules: system-wide snapshot of every other module --}}
<div class="dash-section">
    <div class="admin-section-head"><div><span class="admin-kicker">System-wide</span><h2>Platform modules</h2></div></div>
</div>

<div class="dash-cols-2 dash-section-sub">
    <section class="card" aria-labelledby="users-title">
        <div class="admin-section-head"><div><span class="admin-kicker">Accounts</span><h2 id="users-title">Users &amp; roles</h2></div><a class="stat-link" href="{{ route('admin.users.index') }}">Manage users <span aria-hidden="true">&rarr;</span></a></div>
        <div class="dash-mini-stats">
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['users']['total']) }}</strong><span>Total users</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['users']['active']) }}</strong><span>Active</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['users']['disabled']) }}</strong><span>Disabled</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['users']['archived']) }}</strong><span>Archived</span></div>
        </div>
        <ul class="dash-workload-list">
            @foreach($dashboard['users']['by_role'] as $role)
                <li><span>{{ $role['label'] }}</span><strong>{{ number_format($role['active']) }} active</strong><span class="muted">/ {{ number_format($role['total']) }} total</span></li>
            @endforeach
        </ul>
    </section>

    <section class="card" aria-labelledby="providers-title">
        <div class="admin-section-head"><div><span class="admin-kicker">Locations</span><h2 id="providers-title">Training providers</h2></div><a class="stat-link" href="{{ route('admin.providers.index') }}">Manage providers <span aria-hidden="true">&rarr;</span></a></div>
        <div class="dash-mini-stats">
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['providers']['total']) }}</strong><span>Total providers</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['providers']['active']) }}</strong><span>Active</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['providers']['inactive']) }}</strong><span>Inactive</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['providers']['archived']) }}</strong><span>Archived</span></div>
        </div>
        @if(count($dashboard['providers']['top']))
            <ul class="dash-workload-list">
                @foreach($dashboard['providers']['top'] as $row)
                    <li><span>{{ $row['name'] }}</span><strong>{{ number_format($row['count']) }} class{{ $row['count'] === 1 ? '' : 'es' }}</strong></li>
                @endforeach
            </ul>
        @else
            <p class="dash-workload-empty">No active providers have any Classes yet.</p>
        @endif
    </section>
</div>

<div class="dash-cols-2 dash-section-sub">
    <section class="card" aria-labelledby="subjects-title">
        <div class="admin-section-head"><div><span class="admin-kicker">Curriculum</span><h2 id="subjects-title">Subjects &amp; question bank</h2></div><a class="stat-link" href="{{ route('admin.subjects.index') }}">Manage subjects <span aria-hidden="true">&rarr;</span></a></div>
        <div class="dash-mini-stats">
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['subjects']['courses_active']) }}</strong><span>Active subjects</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['subjects']['courses_retired']) }}</strong><span>Retired</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['subjects']['courses_without_exam']) }}</strong><span>Without an exam</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['subjects']['questions_total']) }}</strong><span>Total questions</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['subjects']['questions_active']) }}</strong><span>Active questions</span></div>
        </div>
        <p class="dash-note">Question bank: {{ number_format($dashboard['subjects']['questions_mcq']) }} MCQ &middot; {{ number_format($dashboard['subjects']['questions_true_false']) }} True/False &middot; {{ number_format($dashboard['subjects']['questions_input']) }} Input. <a href="{{ route('admin.questions.index') }}">View question bank &rarr;</a></p>
    </section>

    <section class="card" aria-labelledby="groups-title">
        <div class="admin-section-head"><div><span class="admin-kicker">Cohorts</span><h2 id="groups-title">Groups &amp; exam schedules</h2></div><a class="stat-link" href="{{ route('admin.groups.index') }}">Manage groups <span aria-hidden="true">&rarr;</span></a></div>
        <div class="dash-mini-stats">
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['groups']['active']) }}</strong><span>Active groups</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['groups']['schedules_scheduled']) }}</strong><span>Schedules upcoming</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['groups']['schedules_completed']) }}</strong><span>Completed</span></div>
            <div class="dash-mini-stat"><strong>{{ number_format($dashboard['groups']['schedules_cancelled']) }}</strong><span>Cancelled</span></div>
        </div>
        @if(count($dashboard['groups']['top']))
            <ul class="dash-workload-list">
                @foreach($dashboard['groups']['top'] as $row)
                    <li><span>{{ $row['name'] }}</span><strong>{{ number_format($row['count']) }} member{{ $row['count'] === 1 ? '' : 's' }}</strong></li>
                @endforeach
            </ul>
        @else
            <p class="dash-workload-empty">No active groups have members yet.</p>
        @endif
        <p class="dash-note"><a href="{{ route('admin.exam-schedules.index') }}">View exam schedules &rarr;</a></p>
    </section>
</div>

{{-- Upcoming / currently active classes --}}
<section class="card dash-section" aria-labelledby="class-activity-title">
    <div class="admin-section-head"><div><span class="admin-kicker">Near-term</span><h2 id="class-activity-title">Ongoing &amp; upcoming classes</h2></div><a class="stat-link" href="{{ route('admin.classes.index') }}">All classes <span aria-hidden="true">&rarr;</span></a></div>
    @php($nearTermClasses = array_merge($dashboard['upcoming_classes']['ongoing'], $dashboard['upcoming_classes']['upcoming']))
    @if(count($nearTermClasses))
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Class</th><th>Subject</th><th>Start date</th><th>Status</th><th>Proctor</th><th>Instructor</th><th>Enrollments</th></tr></thead>
            <tbody>
                @foreach($nearTermClasses as $row)
                    <tr>
                        <td><a href="{{ $row['url'] }}">{{ $row['class_number'] }}</a></td>
                        <td>{{ $row['course'] }}</td>
                        <td>{{ $row['starts_at']?->format('M j, Y') ?: 'Not scheduled' }}</td>
                        <td><span class="badge {{ $row['status'] }}">{{ $row['status_label'] }}</span></td>
                        <td>{{ $row['proctor'] }}</td>
                        <td>{{ $row['instructor'] }}</td>
                        <td>{{ number_format($row['enrollment_count']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @else
        <p class="dash-workload-empty">No ongoing or upcoming Classes right now.</p>
    @endif
</section>

{{-- Attention required --}}
<section class="dash-section" aria-labelledby="attention-title">
    <div class="admin-section-head"><div><span class="admin-kicker">Needs review</span><h2 id="attention-title">Attention required</h2></div></div>
    @if(count($dashboard['attention']))
        <ul class="dash-attention-list">
            @foreach($dashboard['attention'] as $item)
                <li class="dash-attention-item">
                    <span class="dash-attention-text"><span class="dash-attention-count">{{ number_format($item['count']) }}</span><span class="dash-attention-label">{{ $item['label'] }}</span></span>
                    @if($item['url'])<a class="stat-link" href="{{ $item['url'] }}">Review <span aria-hidden="true">&rarr;</span></a>@endif
                </li>
            @endforeach
        </ul>
    @else
        <div class="dash-attention-empty">Nothing needs attention right now — no unassigned Classes, empty schedules, or stuck attempts.</div>
    @endif
</section>

{{-- Recent activity --}}
<section class="card dash-section" aria-labelledby="recent-activity-title">
    <div class="admin-section-head"><div><span class="admin-kicker">Audit trail</span><h2 id="recent-activity-title">Recent activity</h2></div></div>
    @if(count($dashboard['recent_activity']))
        <ul class="dash-activity-list">
            @foreach($dashboard['recent_activity'] as $event)
                <li class="dash-activity-item">
                    <span class="dash-activity-main"><b>{{ $event['label'] }}</b> by {{ $event['actor'] }}@if($event['subject_type']) &middot; {{ $event['subject_type'] }}@endif</span>
                    <span class="dash-activity-meta">{{ $event['occurred_at']?->format('M j, Y g:i A') }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="dash-activity-empty">No recorded activity yet.</p>
    @endif
</section>

<section class="card dash-section" aria-labelledby="survey-dashboard-title">
    <div class="admin-section-head">
        <div><span class="admin-kicker">Database-backed responses</span><h2 id="survey-dashboard-title">Student Survey Answers</h2></div>
        <span class="muted">{{ number_format($completedSurveyCount) }} completed</span>
    </div>
    <p class="dash-note">Student identity is intentionally not displayed. Click a response to view its answers.</p>

    @if($surveyResponses->isEmpty())
        <p class="dash-activity-empty">No completed student surveys are available yet.</p>
    @else
        <ul class="dash-survey-list">
            @foreach($surveyResponses as $survey)
                <li class="dash-survey-item" x-data="{ open: false }" x-bind:class="{ open: open }">
                    <button class="dash-survey-toggle" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()">
                        <span class="dash-survey-id">#{{ str($survey->public_id)->substr(0, 8)->upper() }}</span>
                        <span class="dash-survey-meta">{{ $survey->schedule?->trainingClass?->class_number ?: 'Class not assigned' }} &middot; {{ $survey->schedule?->exam?->name ?: 'Exam not assigned' }}</span>
                        <span class="dash-survey-date">{{ $survey->completed_at?->format('M j, Y') ?: 'Completed' }}</span>
                        <span class="dash-survey-chevron" x-text="open ? '−' : '+'" aria-hidden="true"></span>
                    </button>
                    <dl class="dash-survey-answers" x-show="open" x-transition x-cloak>
                        @forelse($survey->answers as $answer)
                            <div class="dash-survey-answer-row"><dt>{{ $surveyQuestionLabels[$answer->question_key] ?? $answer->question_key }}</dt><dd>{{ $answer->answer !== null && $answer->answer !== '' ? $answer->answer : 'No answer provided' }}</dd></div>
                        @empty
                            <div class="dash-survey-answer-row"><dd>This completed survey has no stored answers.</dd></div>
                        @endforelse
                    </dl>
                </li>
            @endforeach
        </ul>
        @if($completedSurveyCount > $surveyResponses->count())<p class="dash-note">Showing the {{ $surveyResponses->count() }} most recent completed responses.</p>@endif
    @endif
</section>
@endsection
