<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
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
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', Rule::exists('student_groups', 'id')],
        ];
    }
}
