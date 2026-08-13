<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProviderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    ProviderStatus::Active->value,
                    ProviderStatus::Inactive->value,
                ]),
            ],
        ];
    }
}
