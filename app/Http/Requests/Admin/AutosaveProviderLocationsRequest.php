<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutosaveProviderLocationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $provider = $this->route('provider');

        return [
            'locations' => ['present', 'array'],
            'locations.*.client_key' => ['required', 'string', 'max:100', 'distinct'],
            'locations.*.id' => [
                'nullable',
                'integer',
                $provider
                    ? Rule::exists('training_provider_locations', 'id')->where('training_provider_id', $provider->getKey())
                    : Rule::prohibitedIf(true),
            ],
            'locations.*.location' => ['nullable', 'string', 'max:255'],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('locations', []) as $index => $location) {
                if (filled($location['id'] ?? null) && ! filled($location['location'] ?? null)) {
                    $validator->errors()->add("locations.{$index}.location", 'The location is required.');
                }
            }
        });
    }
}
