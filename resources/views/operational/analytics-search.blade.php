@extends('layouts.operational')

@section('operational-content')
<section class="panel assessment-search-panel">
  <div class="panel-title">Search Options</div>
  <div class="panel-body">
    <form class="search-form assessment-search-form" method="GET" action="{{ route(auth()->user()->hasRole('proctor') ? 'proctor.analytics.results' : 'instructor.analytics.results') }}" x-data="{ courseId: '', role: 'Trainees', dateRange: 'Custom Date Range', startDate: '', endDate: '', submitSearch(event) { const url = new URL(event.currentTarget.action, window.location.origin); new URLSearchParams({ course_id: this.courseId, role: this.role, date_range: this.dateRange, start_date: this.startDate, end_date: this.endDate }).forEach((value, key) => { if (value) url.searchParams.set(key, value); }); window.location.assign(url.toString()); } }" x-on:submit.prevent="submitSearch($event)">
      <div class="form-line"><label><strong>Course(s):</strong></label><select name="course_id" x-model="courseId"><option value=""></option>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach</select></div>
      <div class="form-line"><label><strong>Roles:</strong></label><select name="role" x-model="role"><option>Trainees</option><option>Instructors</option><option>Proctors</option></select></div>
      <div class="form-line"><label><strong>Date Range:</strong></label><div class="assessment-date-range"><select name="date_range" x-model="dateRange"><option>Custom Date Range</option><option>All Time</option><option>Previous 1 Month</option><option>Previous 6 Months</option><option>Previous 1 Year</option></select><input name="start_date" type="date" x-model="startDate" /><input name="end_date" type="date" x-model="endDate" /></div></div>
      <div class="search-actions"><button class="blue-btn" type="submit">Search</button></div>
    </form>
  </div>
</section>
@endsection
