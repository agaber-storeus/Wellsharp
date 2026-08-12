@extends('layouts.admin')
@section('admin-content')
<div class="page-head"><div><span class="admin-kicker">{{ $schedule->exam?->name }}</span><h1>Edit exam schedule</h1></div><a class="btn secondary" href="{{ route('admin.exam-schedules.index') }}">Back to schedules</a></div>
<div class="card"><form method="POST" action="{{ route('admin.exam-schedules.update', $schedule) }}">@csrf @method('PUT') @include('admin.exam-schedules._form')</form></div>
@endsection
