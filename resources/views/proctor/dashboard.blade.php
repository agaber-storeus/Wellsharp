@extends('layouts.operational')

@section('operational-content')
    @include('operational._home', ['roleLabel' => $roleLabel])
@endsection
