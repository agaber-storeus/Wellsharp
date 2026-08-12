<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['wellsharp_id' => strtoupper(trim((string) $this->wellsharp_id))]);
    }

    public function rules(): array
    {
        return [
            'wellsharp_id' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:users,wellsharp_id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'birthday' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'company' => ['nullable', 'string', 'max:180'],
            'position' => ['nullable', 'string', 'max:180'],
            'company_contact' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:64'],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'role_id' => [Rule::requiredIf(fn (): bool => ! $this->routeIs('admin.students.store')), Rule::exists('roles', 'id')],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', Rule::exists('student_groups', 'id')],
        ];
    }
}
