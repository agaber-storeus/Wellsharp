@extends('layouts.app')
@section('content')<div class="welcome card"><h1>WellSharp workspace</h1><p>You are signed in as <strong>{{ auth()->user()->currentRole?->name }}</strong>.</p><p class="muted">This role's operational workspace will be added in the next implementation slice. Your account and session are protected server-side.</p></div>@endsection
