@extends('layouts.admin')
@section('admin-content')<div class="page-head"><h1>Edit class</h1></div><div class="card"><form method="POST" action="{{ route('admin.classes.update', $trainingClass) }}">@csrf @method('PUT') @include('admin.classes._form')</form></div>@endsection
