@extends('layouts.admin')
@section('admin-content')<div class="page-head"><h1>Edit {{ $user->display_name }}</h1></div><div class="card"><form method="POST" action="{{ route('admin.users.update', $user) }}">@csrf @method('PUT') @include('admin.users._form')</form></div>@endsection
