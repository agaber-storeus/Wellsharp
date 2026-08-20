@extends('layouts.admin')
@section('admin-content')<div class="page-head hero"><div><span class="admin-kicker">New class</span><h1>Create class</h1><p>Fields are grouped below for a faster, calmer setup.</p></div></div><form method="POST" action="{{ route('admin.classes.store') }}">@csrf
@include('admin.classes._form')</form>@endsection
