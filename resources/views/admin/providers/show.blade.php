@extends('layouts.admin')

@section('admin-content')
<div class="page-head">
    <h1>{{ $provider->name }}</h1>
    <div class="actions">
        <a class="btn secondary" href="{{ route('admin.providers.edit', $provider) }}">Edit</a>
        @if($provider->archived_at === null)
            <form method="POST" action="{{ route('admin.providers.archive', $provider) }}">
                @csrf @method('PATCH')
                <button class="btn danger" type="submit">Archive</button>
            </form>
        @endif
    </div>
</div>

<div class="card">
    <div class="form-grid">
        <div><span class="muted">Provider number</span><p>{{ $provider->provider_number }}</p></div>
        <div>
            <span class="muted">Status</span>
            <p><span class="badge {{ $provider->status->value }}">{{ $provider->status->label() }}</span></p>
        </div>
        <div><span class="muted">Email</span><p>{{ $provider->email ?: 'Not set' }}</p></div>
        <div><span class="muted">Phone</span><p>{{ $provider->phone ?: 'Not set' }}</p></div>
        <div class="full"><span class="muted">Address</span><p>{{ $provider->address ?: 'Not set' }}</p></div>
        <div><span class="muted">Latitude</span><p>{{ $provider->latitude ?? 'Not set' }}</p></div>
        <div><span class="muted">Longitude</span><p>{{ $provider->longitude ?? 'Not set' }}</p></div>
    </div>
</div>

<div class="card">
    <div class="admin-section-head">
        <div><h2>Provider location</h2><p class="muted">Saved map location for this training provider.</p></div>
        @if($provider->address)<span class="muted">{{ $provider->address }}</span>@endif
    </div>
    @if($provider->latitude !== null && $provider->longitude !== null)
        <div id="providerLocationMap" class="provider-location-map provider-location-map-view" data-lat="{{ $provider->latitude }}" data-lng="{{ $provider->longitude }}" data-address="{{ $provider->address ?: $provider->name }}" aria-label="Map showing {{ $provider->name }}"></div>
    @else
        <div class="muted provider-location-empty">No map location has been saved for this provider. <a href="{{ route('admin.providers.edit', $provider) }}">Add a location</a>.</div>
    @endif
</div>
@endsection
