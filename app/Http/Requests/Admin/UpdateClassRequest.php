<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Rules\ActiveStaffWithRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $trainingClass = $this->route('trainingClass');

        return [
            'class_number' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('classes', 'class_number')->ignore($trainingClass)],
            'course_id' => ['required', Rule::exists('courses', 'id')],
            'training_provider_id' => ['nullable', Rule::exists('training_providers', 'id')],
            'proctor_id' => ['required', 'integer', Rule::exists('users', 'id'), new ActiveStaffWithRole(Role::PROCTOR, 'Proctor')],
            'instructor_id' => ['required', 'integer', Rule::exists('users', 'id'), new ActiveStaffWithRole(Role::INSTRUCTOR, 'Instructor')],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
