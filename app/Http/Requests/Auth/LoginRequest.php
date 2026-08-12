<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['wellsharp_id' => strtoupper(trim((string) $this->wellsharp_id))]);
    }

    public function rules(): array
    {
        return ['wellsharp_id' => ['required', 'string', 'max:64'], 'password' => ['required', 'string']];
    }
}
