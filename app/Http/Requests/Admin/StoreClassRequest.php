<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'class_number' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:classes,class_number'],
            'course_id' => ['required', Rule::exists('courses', 'id')],
            'training_provider_id' => ['nullable', Rule::exists('training_providers', 'id')],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
