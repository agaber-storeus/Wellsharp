<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Models\Role;
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

        // Update never accepts a role change (that's ChangeUserRoleAction's
        // job, via a separate endpoint), so the effective role is always the
        // user's current one - never a client-supplied value.
        $passwordRules = Role::passwordLengthRules($user?->currentRole?->key);

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:32'],
            // Age is derived from birthday (see UserProfile::calculateAge()),
            // never accepted as its own input, so the bounds that used to
            // live on 'age' (1-120) are enforced here instead.
            'birthday' => ['nullable', 'date', 'before:today', 'after:'.now()->subYears(120)->toDateString()],
            'address' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'company' => ['nullable', 'string', 'max:180'],
            'position' => ['nullable', 'string', 'max:180'],
            'company_contact' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:64'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'password' => ['nullable', 'string', 'confirmed', ...$passwordRules],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', Rule::exists('student_groups', 'id')],
        ];
    }
}
