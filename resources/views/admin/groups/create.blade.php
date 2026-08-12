@extends('layouts.admin')
@section('admin-content')<div class="page-head"><div><span class="admin-kicker">Group management</span><h1>Create group</h1></div><a class="btn secondary" href="{{ route('admin.groups.index') }}">Back to groups</a></div><div class="card"><form method="POST" action="{{ route('admin.groups.store') }}">@csrf @include('admin.groups._form')</form></div>@endsection
