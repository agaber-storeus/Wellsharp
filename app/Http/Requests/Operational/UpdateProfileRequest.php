<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('proctor') || $this->user()?->hasRole('instructor');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'birthday' => $this->filled('birthday_month') && $this->filled('birthday_day') && $this->filled('birthday_year')
                ? sprintf('%04d-%02d-%02d', $this->input('birthday_year'), $this->input('birthday_month'), $this->input('birthday_day'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'phone' => ['nullable', 'string', 'max:32'],
            'birthday' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'company' => ['nullable', 'string', 'max:180'],
            'position' => ['nullable', 'string', 'max:180'],
            'employee_id' => ['nullable', 'string', 'max:64'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'password' => ['nullable', 'string', 'min:5', 'max:8', 'confirmed'],
        ];
    }
}
