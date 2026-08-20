@extends('layouts.admin')
@section('admin-content')<div class="admin-page-head hero"><div><h1>Edit provider</h1></div></div><form method="POST" action="{{ route('admin.providers.update', $provider) }}">@csrf @method('PUT') @include('admin.providers._form')</form>@endsection
