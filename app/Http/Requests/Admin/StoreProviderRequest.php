<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'provider_number' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:training_providers,provider_number'],
            'name' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'], 'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'draft_token' => ['nullable', 'uuid'],
            'locations' => ['nullable', 'array'],
            'locations.*.client_key' => ['nullable', 'string', 'max:100', 'distinct'],
            'locations.*.id' => ['nullable', 'integer'],
            'locations.*.location' => ['required', 'string', 'max:255'],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
