<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignClassStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return ['user_id' => ['required', Rule::exists('users', 'id')], 'assignment_role' => ['required', Rule::in(['proctor', 'instructor'])]];
    }
}
