<?php

namespace App\Actions\TrainingProviders;

use App\Models\TrainingProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AutosaveProviderLocationsAction
{
    public function execute(TrainingProvider $provider, array $locations): array
    {
        return DB::transaction(function () use ($provider, $locations): array {
            $keptIds = [];
            $saved = [];

            foreach ($locations as $data) {
                $clientKey = (string) $data['client_key'];
                $name = trim((string) ($data['location'] ?? ''));

                if ($name === '') {
                    $saved[] = $this->payload($clientKey, null, $data);
                    continue;
                }

                $location = $provider->locations()->find(Arr::get($data, 'id')) ?? $provider->locations()->make();
                $location->fill([
                    'location' => $name,
                    'latitude' => filled($data['latitude'] ?? null) ? $data['latitude'] : null,
                    'longitude' => filled($data['longitude'] ?? null) ? $data['longitude'] : null,
                    'is_active' => true,
                ])->save();

                $keptIds[] = $location->getKey();
                $saved[] = $this->payload($clientKey, $location->getKey(), $location->toArray());
            }

            $provider->locations()->where('is_active', true)->whereNotIn('id', $keptIds)->update(['is_active' => false]);

            $primary = $provider->locations()->where('is_active', true)->oldest('id')->first();
            if ($primary) {
                $provider->forceFill([
                    'address' => $primary->location,
                    'latitude' => $primary->latitude,
                    'longitude' => $primary->longitude,
                ])->save();
            }

            return $saved;
        });
    }

    private function payload(string $clientKey, ?int $id, array $data): array
    {
        return [
            'client_key' => $clientKey,
            'id' => $id,
            'location' => $data['location'] ?? '',
            'latitude' => filled($data['latitude'] ?? null) ? (float) $data['latitude'] : null,
            'longitude' => filled($data['longitude'] ?? null) ? (float) $data['longitude'] : null,
        ];
    }
}
