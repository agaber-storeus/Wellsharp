@extends('layouts.admin')
@section('admin-content')
<div class="page-head hero"><div><span class="admin-kicker">{{ $schedule->exam?->name }}</span><h1>Edit exam schedule</h1></div><div class="admin-page-actions"><a class="btn secondary" href="{{ route('admin.exam-schedules.index') }}">Back to schedules</a></div></div>
<form method="POST" action="{{ route('admin.exam-schedules.update', $schedule) }}">@csrf @method('PUT') @include('admin.exam-schedules._form')</form>
@endsection
