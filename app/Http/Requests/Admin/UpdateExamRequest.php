<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateExamRequest extends StoreExamRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['code'] = ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('exams', 'code')->ignore($this->route('exam'))];

        return $rules;
    }
}
