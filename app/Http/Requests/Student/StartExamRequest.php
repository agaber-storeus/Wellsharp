<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StartExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Blank/omitted means the Question bank's original source language.
            // Whether a non-blank value is actually an Admin-enabled language is
            // checked in StartExamAttemptAction, alongside its other
            // attempt-eligibility checks - not here, since it depends on live
            // TranslationLanguage state rather than input shape.
            'language_code' => ['nullable', 'string', 'max:16'],
        ];
    }
}
