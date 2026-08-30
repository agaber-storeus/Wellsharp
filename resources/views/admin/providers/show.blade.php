@extends('layouts.admin')

@section('admin-content')
<div class="admin-page-head hero">
    <div><h1>{{ $provider->name }}</h1></div>
    <div class="admin-page-actions">
        <a class="btn secondary" href="{{ route('admin.providers.edit', $provider) }}">Edit</a>
        @if($provider->archived_at === null && $provider->status->value !== 'archived')
            <form method="POST" action="{{ route('admin.providers.archive', $provider) }}">
                @csrf @method('PATCH')
                <button class="btn danger" type="submit">Archive</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.providers.unarchive', $provider) }}">
                @csrf @method('PATCH')
                <button class="btn secondary" type="submit">Unarchive</button>
            </form>
        @endif
    </div>
</div>

<div class="admin-bento">

    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon">🏢</span><h3>Details</h3><span style="margin-left:auto;display:flex;align-items:center;gap:6px"><span class="muted">Status</span><span class="badge {{ $provider->status->value }}">{{ $provider->status->label() }}</span></span></div>
        <div class="admin-meta-grid">
            <div class="admin-meta-item"><span class="muted">Provider number</span><strong>{{ $provider->provider_number }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Email</span><strong>{{ $provider->email ?: 'Not set' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Phone</span><strong>{{ $provider->phone ?: 'Not set' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Address</span><strong>{{ $provider->address ?: 'Not set' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Latitude</span><strong>{{ $provider->latitude ?? 'Not set' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Longitude</span><strong>{{ $provider->longitude ?? 'Not set' }}</strong></div>
        </div>
    </div>

    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon cool">📍</span><h3>Provider location</h3>@if($provider->address)<span class="muted" style="margin-left:auto">{{ $provider->address }}</span>@endif</div>
        <p class="admin-card-note">Saved map location for this training provider.</p>
        @if($provider->latitude !== null && $provider->longitude !== null)
            <div id="providerLocationMap" class="provider-location-map provider-location-map-view" data-lat="{{ $provider->latitude }}" data-lng="{{ $provider->longitude }}" data-address="{{ $provider->address ?: $provider->name }}" aria-label="Map showing {{ $provider->name }}"></div>
        @else
            <div class="muted provider-location-empty">No map location has been saved for this provider. <a href="{{ route('admin.providers.edit', $provider) }}">Add a location</a>.</div>
        @endif
    </div>

</div>
@endsection
