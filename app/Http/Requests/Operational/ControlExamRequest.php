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
            'proctor_id' => ['required', 'string', 'max:32'],
        ];
    }
}
