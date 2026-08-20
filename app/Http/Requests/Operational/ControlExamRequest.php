<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ControlExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('proctor') || $this->user()?->hasRole('instructor');
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['start', 'end'])],
            // A Proctor controls directly with no credential. An Instructor must supply
            // an active Proctor's ID as a dual-control/oversight check.
            'proctor_id' => [Rule::requiredIf(fn (): bool => $this->user()?->hasRole('instructor') === true), 'nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'proctor_id.required' => "Proctor's ID is required.",
            'proctor_id.string' => "Enter a valid Proctor's ID.",
            'proctor_id.max' => "Enter a valid Proctor's ID.",
        ];
    }
}
