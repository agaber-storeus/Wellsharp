@extends('layouts.operational')

@section('operational-content')
@php($operationalPrefix = auth()->user()->hasRole('proctor') ? 'proctor' : 'instructor')
<section class="panel assessment-search-panel">
  <div class="panel-title">Scores &amp; Reports</div>
  <div class="panel-body">
    <p class="report-explanation">Scores are calculated from each saved answer. Release finalizes the trainee exam in its current state, scores unanswered questions as incorrect, and records who released it.</p>
    <form class="search-form assessment-search-form" method="GET" action="{{ route($operationalPrefix.'.analytics.results') }}">
      <div class="form-line"><label><strong>Subject:</strong></label><select name="course_id"><option value="">All Subjects</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected((string) ($filters['course_id'] ?? '') === (string) $course->id)>{{ $course->name }}</option>@endforeach</select></div>
      <div class="form-line"><label><strong>Date Range:</strong></label><div class="assessment-date-range"><select name="date_range"><option value="Custom Date Range" @selected(($filters['date_range'] ?? 'Custom Date Range') === 'Custom Date Range')>Custom Date Range</option><option value="All Time" @selected(($filters['date_range'] ?? '') === 'All Time')>All Time</option><option value="Previous 1 Month" @selected(($filters['date_range'] ?? '') === 'Previous 1 Month')>Previous 1 Month</option><option value="Previous 6 Months" @selected(($filters['date_range'] ?? '') === 'Previous 6 Months')>Previous 6 Months</option><option value="Previous 1 Year" @selected(($filters['date_range'] ?? '') === 'Previous 1 Year')>Previous 1 Year</option></select><input name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}" /><input name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}" /></div></div>
      <div class="search-actions"><button class="blue-btn" type="submit">Search Reports</button><a class="export-btn" href="{{ route($operationalPrefix.'.analytics.results') }}">Clear</a></div>
    </form>
  </div>
</section>

<section class="report-section">
  <div class="report-section-head"><h2 class="assessment-title">Assessment Comparison</h2><span>{{ $results->count() }} assessments</span></div>
  <div class="report-table-wrap"><table class="assessment-table report-table"><thead><tr><th>Assessment</th><th>Trainees Assessed</th><th># Passed</th><th># Failed</th><th>Passing Rate</th><th>Average Score</th><th>Trainees Retaking Exam</th><th>Retake # Passed</th><th>Retake # Failed</th><th>Retake Passing Rate</th><th>Retake Average Score</th></tr></thead><tbody>@forelse($results as $result)<tr><td><strong>{{ $result['name'] }}</strong><br><small>{{ $result['subject'] }}</small></td><td>{{ $result['trainees'] }}</td><td>{{ $result['passed'] }}</td><td>{{ $result['failed'] }}</td><td>{{ $result['rate'] }}</td><td>{{ $result['average'] }}</td><td>{{ $result['retaking'] }}</td><td>{{ $result['retake_passed'] }}</td><td>{{ $result['retake_failed'] }}</td><td>{{ $result['retake_rate'] }}</td><td>{{ $result['retake_average'] }}</td></tr>@empty<tr><td colspan="11">No scored assessment data is available for the selected filters.</td></tr>@endforelse</tbody></table></div>
</section>

<section class="report-section">
  <div class="report-section-head"><h2 class="assessment-title">Trainee Scores</h2><span>{{ $attemptRows->count() }} attempts</span></div>
  <div class="report-table-wrap"><table class="assessment-table report-table trainee-report-table"><thead><tr><th>Trainee</th><th>Assessment</th><th>Attempt</th><th>Score</th><th>Result</th><th>State</th><th>Released</th><th>Actions</th></tr></thead><tbody>@forelse($attemptRows as $attempt)<tr><td><strong>{{ $attempt->student?->display_name ?: $attempt->student?->wellsharp_id }}</strong><br><small>{{ $attempt->student?->wellsharp_id }}</small></td><td>{{ $attempt->exam?->name ?: 'Assessment' }}</td><td>{{ $attempt->attempt_number }}</td><td>{{ $attempt->score !== null ? number_format((float) $attempt->score, 2).'%' : 'Pending' }}</td><td>@if($attempt->passed === true)<span class="report-result passed">Passed</span>@elseif($attempt->passed === false)<span class="report-result failed">Failed</span>@else<span class="report-result pending">Pending</span>@endif</td><td>{{ $attempt->status->label() }}</td><td>{{ $attempt->released_at?->format('Y-m-d H:i') ?: 'Not released' }}</td><td><div class="report-actions"><a class="tiny-blue" href="{{ route($operationalPrefix.'.analytics.attempts.show', $attempt) }}">View report</a>@if(!$attempt->released_at)<form method="POST" action="{{ route($operationalPrefix.'.analytics.attempts.release', $attempt) }}" onsubmit="return confirm('Release this trainee exam in its current state? Unanswered questions will be scored incorrect.');">@csrf<button class="tiny-green" type="submit">Release</button></form>@endif</div></td></tr>@empty<tr><td colspan="8">No trainee attempts are available for the selected filters.</td></tr>@endforelse</tbody></table></div>
</section>
@endsection
