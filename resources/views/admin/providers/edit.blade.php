@extends('layouts.admin')
@section('admin-content')<div class="page-head"><h1>Edit provider</h1></div><div class="card"><form method="POST" action="{{ route('admin.providers.update', $provider) }}">@csrf @method('PUT') @include('admin.providers._form')</form></div>@endsection
