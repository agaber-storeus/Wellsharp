<?php

namespace App\Http\Requests\Admin;

use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamQuestionSelectionMode;
use App\Models\Course;
use App\Models\Group;
use App\Models\Role;
use App\Rules\ActiveStaffWithRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $selectionMode = $this->input('question_selection_mode') ?: ExamQuestionSelectionMode::Manual->value;

        $this->merge([
            'question_selection_mode' => $selectionMode,
            'question_order_mode' => $selectionMode === ExamQuestionSelectionMode::Random->value
                ? ExamQuestionOrderMode::Static->value
                : ($this->input('question_order_mode') ?: ExamQuestionOrderMode::Static->value),
            'start_mode' => $this->input('start_mode') ?: 'automatic',
        ]);
    }

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
            'question_selection_mode' => ['required', Rule::in(array_column(ExamQuestionSelectionMode::cases(), 'value'))],
            'question_count' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published,archived'],
            'start_mode' => ['nullable', 'in:automatic,manual'],
            'question_ids' => ['required_if:question_selection_mode,manual', 'array', 'min:1'],
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
            'proctor_id' => ['nullable', 'integer', Rule::exists('users', 'id'), new ActiveStaffWithRole(Role::PROCTOR, 'Proctor')],
            'instructor_id' => ['nullable', 'integer', Rule::exists('users', 'id'), new ActiveStaffWithRole(Role::INSTRUCTOR, 'Instructor')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $course = Course::query()->find($this->input('course_id')) ?: $this->route('course');
            $ids = array_map('intval', $this->input('question_ids', []));
            $selectionMode = $this->input('question_selection_mode', ExamQuestionSelectionMode::Manual->value);

            if ($selectionMode === ExamQuestionSelectionMode::Manual->value) {
                if ($course && count($ids) !== count(array_unique($ids))) {
                    $validator->errors()->add('question_ids', 'A question may only be selected once.');
                }
                if ($course && count($course->questions()->whereIn('id', $ids)->get()) !== count($ids)) {
                    $validator->errors()->add('question_ids', 'Every exam question must belong to the selected Subject.');
                }
            } elseif ($course) {
                $eligibleCount = $course->questions()->where('is_active', true)->count();
                $questionCount = $this->input('question_count');

                if (! filled($questionCount)) {
                    $validator->errors()->add('question_count', 'Enter the number of questions for random selection.');
                } elseif (is_numeric($questionCount) && (int) $questionCount > $eligibleCount) {
                    $validator->errors()->add('question_count', "The selected Subject has only {$eligibleCount} active questions available.");
                }
            }

            if ($selectionMode === ExamQuestionSelectionMode::Manual->value && $this->input('question_order_mode') === 'static') {
                $orders = array_values(array_filter($this->input('display_orders', []), fn ($value): bool => $value !== null && $value !== ''));
                if (count($orders) !== count(array_unique(array_map('intval', $orders)))) {
                    $validator->errors()->add('display_orders', 'Static question order numbers must be unique.');
                }
            }

            // Group/date fields are an optional inline bundle: fill them in to schedule
            // this Exam's first Class in the same save, or leave them blank and schedule
            // later (or schedule additional Groups) from the Exam Schedules screen.
            $touchedSchedule = collect(['group_id', 'start_date', 'end_date', 'duration_minutes', 'proctor_id', 'instructor_id'])->contains(fn (string $field): bool => $this->filled($field));
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
            if (! $this->filled('proctor_id')) {
                $validator->errors()->add('proctor_id', 'Select a Proctor to schedule this Exam\'s first Class.');
            }
            if (! $this->filled('instructor_id')) {
                $validator->errors()->add('instructor_id', 'Select an Instructor to schedule this Exam\'s first Class.');
            }
        });
    }
}
