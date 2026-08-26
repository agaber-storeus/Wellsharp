<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamTranslationLanguagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'enabled_ids' => ['present', 'array'],
            'enabled_ids.*' => ['integer', 'distinct', 'exists:translation_languages,id'],
        ];
    }
}
