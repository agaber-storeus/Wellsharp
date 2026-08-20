@extends('layouts.admin')
@section('admin-content')<div class="admin-page-head hero"><div><h1>Add training provider</h1></div></div><form method="POST" action="{{ route('admin.providers.store') }}">@csrf
@include('admin.providers._form')</form>@endsection
