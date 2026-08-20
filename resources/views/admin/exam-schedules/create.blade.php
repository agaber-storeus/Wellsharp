@extends('layouts.admin')
@section('admin-content')
<div class="page-head hero"><div><span class="admin-kicker">Exam Management</span><h1>Create exam schedule</h1><p>Place a published exam on the calendar for one active group.</p></div></div>
<form method="POST" action="{{ route('admin.exam-schedules.store') }}">@csrf @include('admin.exam-schedules._form')</form>
@endsection
