@extends('layouts.admin')
@section('admin-content')<div class="page-head"><h1>Add training provider</h1></div><div class="card"><form method="POST" action="{{ route('admin.providers.store') }}">@csrf
@include('admin.providers._form')</form></div>@endsection
