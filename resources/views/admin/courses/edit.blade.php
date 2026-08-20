@extends('layouts.admin')
@section('admin-content')<div class="admin-page-head hero"><div><h1>Edit Subject</h1></div></div><form method="POST" action="{{ route('admin.courses.update', $course) }}">@csrf @method('PUT') @include('admin.courses._form')</form>@endsection
