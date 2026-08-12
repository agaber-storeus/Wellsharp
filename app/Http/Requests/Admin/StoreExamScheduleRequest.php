<?php

namespace App\Http\Requests\Admin;

use App\Models\Exam;
use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreExamScheduleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('exam_id') && $this->route('exam')) {
            $this->merge(['exam_id' => $this->route('exam')->getKey()]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'group_id' => ['required', 'integer', 'exists:student_groups,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $exam = Exam::query()->find($this->input('exam_id'));
            $group = Group::query()->find($this->input('group_id'));
            if (! $exam || $exam->status->value !== 'published') {
                $validator->errors()->add('exam_id', 'Select an active published exam.');
            }
            if (! $group || $group->status->value !== 'active') {
                $validator->errors()->add('group_id', 'Select an active group.');
            }
            if (! $this->filled('duration_minutes')) {
                $validator->errors()->add('duration_minutes', 'Provide the time allowed for each student.');
            }
        });
    }
}
