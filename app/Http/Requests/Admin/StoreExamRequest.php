<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use App\Models\Group;
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
            'certificate_validity_years' => ['nullable', 'integer', 'min:1', 'max:99'],
            'question_order_mode' => ['required', 'in:static,shuffle'],
            'status' => ['required', 'in:draft,published,archived'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', Rule::exists('questions', 'id')],
            'display_orders' => ['nullable', 'array'],
            'display_orders.*' => ['nullable', 'integer', 'min:1'],
            // Scheduling fields: the Exam is scheduled for a Group in this same
            // request when it doesn't have a schedule yet, so Admin never has to
            // visit a separate "Create Exam Schedule" screen for the first Class.
            'group_id' => ['nullable', 'integer', Rule::exists('student_groups', 'id')],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
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

            // Group/date fields are an optional inline bundle: fill them in to schedule
            // this Exam's first Class in the same save, or leave them blank and schedule
            // later (or schedule additional Groups) from the Exam Schedules screen.
            $touchedSchedule = collect(['group_id', 'start_date', 'end_date', 'duration_minutes'])->contains(fn (string $field): bool => $this->filled($field));
            if (! $touchedSchedule) {
                return;
            }
            if (! $this->filled('group_id')) {
                $validator->errors()->add('group_id', 'Select a Group to schedule this Exam for.');
            } else {
                $group = Group::query()->find($this->input('group_id'));
                if (! $group || $group->status->value !== 'active') {
                    $validator->errors()->add('group_id', 'Select an active Group.');
                }
            }
            if (! $this->filled('start_date')) {
                $validator->errors()->add('start_date', 'Provide a start date.');
            }
            if (! $this->filled('end_date')) {
                $validator->errors()->add('end_date', 'Provide an end date.');
            }
            if (! $this->filled('duration_minutes')) {
                $validator->errors()->add('duration_minutes', 'Provide the time allowed for each student.');
            }
        });
    }
}
