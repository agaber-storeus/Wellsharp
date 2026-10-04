@php
    $savedLocations = $provider->relationLoaded('locations') ? $provider->locations->where('is_active', true)->values() : collect();
    $locationRows = old('locations', $autosaveLocations ?? $savedLocations->map(fn ($location) => ['id' => $location->id, 'location' => $location->location, 'latitude' => $location->latitude, 'longitude' => $location->longitude])->all());
    if ($locationRows === [] && filled($provider->address)) {
        $locationRows = [['location' => $provider->address, 'latitude' => $provider->latitude, 'longitude' => $provider->longitude]];
    }
    $locationRows = collect($locationRows)->map(function ($location) {
        $location['clientKey'] = $location['client_key'] ?? (filled($location['id'] ?? null) ? 'saved-'.$location['id'] : 'new-'.(string) \Illuminate\Support\Str::uuid());
        $location['autosaveStatus'] = 'saved';
        return $location;
    })->values()->all();
@endphp
<div class="admin-bento" x-data="providerLocationForm({ locations: @js($locationRows), autosaveUrl: @js($autosaveUrl) })" x-on:provider-location-selected.window="selectLocation($event.detail.key)" x-on:provider-location-coordinate-changed.window="autosaveLocation($event.detail.key, true)">
    @if($draftToken)<input type="hidden" name="draft_token" value="{{ $draftToken }}">@endif
    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon">&#127970;</span><h3>Details</h3></div>
        <div class="admin-field-grid">
            <div class="field"><x-admin.label for="provider_number" required>Provider number</x-admin.label><input id="provider_number" name="provider_number" value="{{ old('provider_number', $provider->provider_number) }}" required></div>
            <div class="field"><x-admin.label for="name" required>Name</x-admin.label><input id="name" name="name" value="{{ old('name', $provider->name) }}" required></div>
            <div class="field"><x-admin.label for="email">Email</x-admin.label><input id="email" type="email" name="email" value="{{ old('email', $provider->email) }}"></div>
            <div class="field"><x-admin.label for="phone">Phone</x-admin.label><input id="phone" name="phone" value="{{ old('phone', $provider->phone) }}"></div>
        </div>
    </div>
    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon cool">&#128205;</span><h3>Locations</h3><button class="btn secondary small provider-location-add" type="button" x-on:click="addLocation()">Add location</button></div>
        <p class="admin-card-note">Add every location where this provider delivers training. Removed locations remain available to historical Classes and schedules.</p>
        <div class="provider-location-grid provider-location-grid--editor">
            <template x-for="(location, index) in locations" :key="location.clientKey">
                <div class="provider-location-card provider-location-row" x-bind:data-location-key="location.clientKey" x-bind:class="{ 'is-map-selected': selectedLocationKey === location.clientKey }" x-on:click="window.dispatchEvent(new CustomEvent('provider-location-selected', { detail: { key: location.clientKey } }))">
                    <div class="provider-location-card-head">
                        <div class="provider-location-state"><span class="provider-location-state-dot" x-bind:class="{ 'is-pinned': location.latitude !== null && location.latitude !== '' && location.longitude !== null && location.longitude !== '' }"></span><span x-text="location.latitude !== null && location.latitude !== '' && location.longitude !== null && location.longitude !== '' ? 'Pinned' : 'Not pinned'"></span></div>
                        <div class="provider-location-card-statuses"><span class="provider-location-save-status" x-bind:class="'is-' + location.autosaveStatus" x-text="location.autosaveStatus === 'saving' ? 'Saving...' : (location.autosaveStatus === 'error' ? 'Error' : 'Saved')"></span><span class="provider-location-selected-badge" x-show="selectedLocationKey === location.clientKey" x-cloak>Selected</span></div>
                    </div>
                    <div class="field provider-location-input"><label x-bind:for="'location-'+location.clientKey">Location</label><input x-bind:id="'location-'+location.clientKey" x-bind:name="'locations['+index+'][location]'" x-model="location.location" x-on:input.debounce.600ms="autosaveLocation(location.clientKey)" x-bind:data-location-key="location.clientKey" data-location-field="name" maxlength="255" required></div>
                    <div class="provider-location-card-actions">
                        <button class="btn secondary small" type="button">Focus on map</button>
                        <button class="btn secondary small" type="button" x-bind:disabled="location.latitude === null || location.latitude === '' || location.longitude === null || location.longitude === ''" x-on:click.stop="window.dispatchEvent(new CustomEvent('provider-location-clear', { detail: { key: location.clientKey } }))">Clear pin</button>
                        <button class="btn secondary small provider-location-remove" type="button" x-on:click.stop="removeLocation(index)">Remove</button>
                    </div>
                    <input type="hidden" x-bind:name="'locations['+index+'][client_key]'" x-bind:value="location.clientKey">
                    <input type="hidden" x-bind:name="'locations['+index+'][id]'" x-bind:value="location.id || ''">
                    <input type="hidden" x-bind:name="'locations['+index+'][latitude]'" x-model="location.latitude" x-bind:data-location-key="location.clientKey" data-location-field="latitude">
                    <input type="hidden" x-bind:name="'locations['+index+'][longitude]'" x-model="location.longitude" x-bind:data-location-key="location.clientKey" data-location-field="longitude">
                </div>
            </template>
            <p class="muted provider-location-empty-card" x-show="locations.length === 0" x-cloak>No locations added.</p>
        </div>
    </div>
    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon cool">&#128205;</span><h3>Provider location on map</h3></div>
        <div class="field full provider-location-picker"><div class="location-picker-help">Select a location, then click the map or drag its pin to set coordinates.</div><div id="providerLocationMap" class="provider-location-map"></div><div class="location-picker-actions"><span id="providerLocationMessage" class="muted" role="status">Select a location to place its map pin.</span></div><input id="latitude" type="hidden" name="latitude" x-bind:value="locations[0]?.latitude || ''"><input id="longitude" type="hidden" name="longitude" x-bind:value="locations[0]?.longitude || ''"></div>
    </div>
</div>
<div class="actions" style="margin-top:20px"><button class="btn">Save provider</button><a class="btn secondary" href="{{ $provider->exists ? route('admin.providers.show', $provider) : route('admin.providers.index') }}">Cancel</a></div>
