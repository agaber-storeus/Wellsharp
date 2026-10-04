<?php

namespace App\Actions\TrainingProviders;

use App\Models\TrainingProvider;
use Illuminate\Support\Arr;

class SyncProviderLocationsAction
{
    public function execute(TrainingProvider $provider, array $locations): void
    {
        $keptIds = [];

        foreach ($locations as $data) {
            if (! filled($data['location'] ?? null)) {
                continue;
            }

            $location = $provider->locations()->find(Arr::get($data, 'id')) ?? $provider->locations()->make();
            $location->fill([
                'location' => trim($data['location']),
                'latitude' => filled($data['latitude'] ?? null) ? $data['latitude'] : null,
                'longitude' => filled($data['longitude'] ?? null) ? $data['longitude'] : null,
                'is_active' => true,
            ])->save();
            $keptIds[] = $location->getKey();
        }

        $provider->locations()->whereNotIn('id', $keptIds)->update(['is_active' => false]);

        $primary = $provider->locations()->where('is_active', true)->oldest('id')->first();
        if ($primary) {
            $provider->forceFill([
                'address' => $primary->location,
                'latitude' => $primary->latitude,
                'longitude' => $primary->longitude,
            ])->save();
        }
    }
}
