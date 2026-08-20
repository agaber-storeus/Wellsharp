@extends('layouts.admin')
@section('admin-content')<div class="page-head hero"><div><span class="admin-kicker">Editing class</span><h1>Edit class</h1><p>Update any of the grouped fields below and save.</p></div></div><form method="POST" action="{{ route('admin.classes.update', $trainingClass) }}">@csrf @method('PUT') @include('admin.classes._form')</form>@endsection
