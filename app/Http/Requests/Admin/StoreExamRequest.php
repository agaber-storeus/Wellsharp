<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'course_id' => [$this->route('course') ? 'nullable' : 'required', 'integer', Rule::exists('courses', 'id')],
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('exams', 'code')],
            'description' => ['nullable', 'string'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'retake_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'question_order_mode' => ['required', 'in:static,shuffle'],
            'status' => ['required', 'in:draft,published,archived'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', Rule::exists('questions', 'id')],
            'display_orders' => ['nullable', 'array'],
            'display_orders.*' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $course = Course::query()->find($this->input('course_id')) ?: $this->route('course');
            $ids = array_map('intval', $this->input('question_ids', []));
            if ($course && count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('question_ids', 'A question may only be selected once.');
            }
            if ($course && count($course->questions()->whereIn('id', $ids)->get()) !== count($ids)) {
                $validator->errors()->add('question_ids', 'Every exam question must belong to the selected Subject.');
            }
            if ($this->input('question_order_mode') === 'static') {
                $orders = array_values(array_filter($this->input('display_orders', []), fn ($value): bool => $value !== null && $value !== ''));
                if (count($orders) !== count(array_unique(array_map('intval', $orders)))) {
                    $validator->errors()->add('display_orders', 'Static question order numbers must be unique.');
                }
            }
        });
    }
}
