<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('student_groups', 'code')->ignore($this->route('group'))],
            'description' => ['nullable', 'string'],
        ];
    }
}
