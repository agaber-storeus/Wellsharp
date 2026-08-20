@extends('layouts.admin')
@section('admin-content')<div class="admin-page-head hero"><div><span class="admin-kicker">Editing account</span><h1>Edit {{ $user->display_name }}</h1><p>Update any of the grouped fields below and save.</p></div></div><form method="POST" action="{{ route('admin.users.update', $user) }}">@csrf @method('PUT') @include('admin.users._form')</form>@endsection
