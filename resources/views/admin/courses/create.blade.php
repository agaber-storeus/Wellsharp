@extends('layouts.admin')
@section('admin-content')<div class="admin-page-head hero"><div><h1>Create Subject</h1></div></div><form method="POST" action="{{ route('admin.courses.store') }}">@csrf @include('admin.courses._form')</form>@endsection
