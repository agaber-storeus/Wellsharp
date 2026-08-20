@extends('layouts.admin')
@section('admin-content')<div class="admin-page-head hero"><div><span class="admin-kicker">New account</span><h1>Create user</h1><p>Fields are grouped below for a faster, calmer setup.</p></div></div><form method="POST" action="{{ route('admin.users.store') }}">@csrf
@include('admin.users._form')</form>@endsection
