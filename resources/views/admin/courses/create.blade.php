@extends('layouts.admin')
@section('admin-content')<div class="page-head"><h1>Create Subject</h1></div><div class="card"><form method="POST" action="{{ route('admin.courses.store') }}">@csrf @include('admin.courses._form')</form></div>@endsection
