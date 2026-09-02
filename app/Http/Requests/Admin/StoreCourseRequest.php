<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:courses,code'],
            'name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string'],
            'course_level_id' => ['nullable', Rule::exists('course_levels', 'id')],
            'stack_ids' => ['array'], 'stack_ids.*' => [Rule::exists('stacks', 'id')],
            'supplement_ids' => ['array'], 'supplement_ids.*' => [Rule::exists('supplements', 'id')],
            'language_ids' => ['array'], 'language_ids.*' => [Rule::exists('languages', 'id')],
        ];
    }
}
