@extends('layouts.admin')
@section('admin-content')<div class="page-head"><h1>Edit Subject</h1></div><div class="card"><form method="POST" action="{{ route('admin.courses.update', $course) }}">@csrf @method('PUT') @include('admin.courses._form')</form></div>@endsection
