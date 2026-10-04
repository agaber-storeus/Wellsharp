<?php

namespace App\Http\Requests\Admin;

use App\Enums\KnowledgeControlType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeScoreControlRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() === true; }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(KnowledgeControlType::class)],
            'numeric_value' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('type'), ['question_points', 'score_adjustment', 'final_score'], true)),
                'nullable', 'numeric',
                Rule::when($this->input('type') !== 'score_adjustment', ['min:0', 'max:100']),
                Rule::when($this->input('type') === 'score_adjustment', ['between:-100,100', 'not_in:0']),
            ],
            'boolean_value' => [Rule::requiredIf($this->input('type') === 'pass_fail'), 'nullable', 'boolean'],
            'exam_attempt_question_id' => [Rule::requiredIf($this->input('type') === 'question_points'), 'nullable', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
