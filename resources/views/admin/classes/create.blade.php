@extends('layouts.admin')
@section('admin-content')<div class="page-head"><h1>Create class</h1></div><div class="card"><form method="POST" action="{{ route('admin.classes.store') }}">@csrf
@include('admin.classes._form')</form></div>@endsection
