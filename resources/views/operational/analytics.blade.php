@extends('layouts.operational')

@section('operational-content')
@php($operationalPrefix = auth()->user()->hasRole('proctor') ? 'proctor' : 'instructor')
<form class="analytics-top" method="GET" action="{{ route($operationalPrefix.'.analytics') }}"><strong>Date Range:</strong><select name="date_range"><option value="All Time" @selected(request('date_range', 'All Time') === 'All Time')>All Time</option><option value="Custom Date Range" @selected(request('date_range') === 'Custom Date Range')>Custom Date Range</option><option value="Previous 1 Month" @selected(request('date_range') === 'Previous 1 Month')>Previous 1 Month</option><option value="Previous 6 Months" @selected(request('date_range') === 'Previous 6 Months')>Previous 6 Months</option></select><strong>Role:</strong><span>{{ str(auth()->user()->currentRole?->key ?: 'staff')->headline() }}</span><button class="blue-btn" type="submit">Search</button></form>
<span class="overview-tab">Overview</span><div class="overview-line"></div>
<div class="class-metrics"><span><strong>{{ $totalClasses }}</strong> Total Classes</span><span><strong>{{ number_format($averageClassSize, 1) }}</strong> Average Class Size</span><span><strong>{{ $classesPerWeek }}</strong> Classes per Week</span><span><strong>{{ $classesPerMonth }}</strong> Classes per Month</span></div>
<div class="class-metrics report-metrics"><span><strong>{{ $scoredAttempts }}</strong> Scored Attempts</span><span><strong>{{ $passedAttempts }}</strong> Passed</span><span><strong>{{ $failedAttempts }}</strong> Failed</span><span><strong>{{ number_format($averageScore, 2) }}%</strong> Average Score</span></div>
<h1 class="classes-over-time-title">Classes Over Time</h1>
<div class="classes-chart-controls"><span><strong>Scope:</strong> All Classes available to eligible staff</span><span><strong>Period:</strong> Month</span><span><strong>Chart:</strong> Bar</span></div>
<section class="classes-time-chart" aria-label="Classes over time chart"><div class="classes-y-axis"><span>8</span><span>7</span><span>6</span><span>5</span><span>4</span><span>3</span><span>2</span><span>1</span><span>0</span></div><div class="classes-bars">@forelse($monthCounts as $month => $count)<div class="week-bar" style="height:{{ min(224, max(32, $count * 32)) }}px"><b>{{ $count }}</b></div>@empty<div class="week-bar zero"><b>0</b></div>@endforelse</div></section>
<div class="classes-chart-dates">@foreach($monthCounts as $month => $count)<span>{{ $month }}[{{ $count }}]</span>@endforeach</div><div class="classes-chart-legend"><span></span>Classes</div>
@endsection
