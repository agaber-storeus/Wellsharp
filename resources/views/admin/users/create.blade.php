@extends('layouts.admin')
@section('admin-content')<div class="page-head"><h1>Create user</h1></div><div class="card"><form method="POST" action="{{ route('admin.users.store') }}">@csrf
@include('admin.users._form')</form></div>@endsection
