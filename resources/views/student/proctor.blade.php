@extends('layouts.student')

@section('student-content')
@php
  $subject = $schedule->exam->subject;
  $courseLevel = $subject->level?->name ?: 'Not configured';
  $stacks = $subject->stacks->pluck('name')->join(', ') ?: 'Not configured';
  $languages = $subject->languages->values();
  $duration = (int) $schedule->duration_minutes;
  $durationText = $duration >= 60 ? intdiv($duration, 60).' Hour'.(intdiv($duration, 60) === 1 ? '' : 's').' '.($duration % 60 ? ($duration % 60).' Minutes' : '') : $duration.' Minutes';
@endphp
<section class="workspace">
  <section class="wide-panel exam-rules">
    <div class="panel-title">Instructions</div>
    <div class="rules-copy">
      <p>You may use the following items during the test:</p>
      <ul>
        <li>Formula sheet supplied by training provider (This may not be part of a course manual.)</li>
        <li>Blank kill sheets and blank paper (for calculation work)</li>
        <li>Reference tables specific to the course level (e.g., weights, pipe or annular displacements/capacities) that may not be part of a course manual</li>
        <li>Handheld calculator--Calculator should be non-programmable type and preferably supplied by training provider.</li>
      </ul>
      <p>Other materials such as your notes may not be used.<br />Cell phones may not be used during the test. Your phone should be turned off for the duration of the exam.<br />The testing database will permit you to change answers, skip questions, and go back to skipped questions. All unanswered questions at the expiration of the testing period will be marked incorrect.<br />During the testing period, you may ask questions if you need clarification of a question. Please indicate to the Proctor that you wish to ask a question. The Proctor will call the instructor, who will answer your question. The Proctor will be present as the instructor answers the question.<br />If you must leave the room for any reason, testing time will continue to decrease. The test will not be paused.<br />When you have completed your exam and submitted your answers, please meet with your instructor for your score report</p>
    </div>
  </section>

  <section class="wide-panel exam-start-panel">
    <div class="exam-course-bar">1. {{ $subject->name }}, {{ $courseLevel }}, {{ $stacks }}</div>
    <div class="test-card">
      <div class="test-card-title">Test Information</div>
      <p><strong>Passing Score:</strong> {{ $schedule->exam->passing_score !== null ? $schedule->exam->passing_score.'% or Greater' : 'Not configured' }}</p>
      <p><strong>Retake Score:</strong> {{ $schedule->exam->retake_score !== null ? $schedule->exam->retake_score.'% or Greater' : 'Not configured' }}</p>
      <p><strong>Test Length:</strong> {{ trim($durationText) ?: 'Not configured' }}</p>
    </div>
    <div class="test-card preference-card">
      <div class="test-card-title">Test Preferences</div>
      <label><strong>Language:</strong>
        <select disabled aria-label="Exam language">@forelse($languages as $language)<option>{{ $language->name }}</option>@empty<option>Not configured</option>@endforelse</select>
      </label>
    </div>
    <form class="start-exam-form" method="POST" action="{{ route('student.exams.start', $schedule) }}">
      @csrf
      <button class="green-btn start-exam-btn" type="submit">Start Exam</button>
    </form>
  </section>
</section>
@endsection
