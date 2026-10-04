<?php

namespace App\Http\Requests\Admin;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Role;
use App\Models\TrainingProviderLocation;
use App\Rules\ActiveStaffWithRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamScheduleRequest extends FormRequest
{
    public const STACK_OPTIONS = ['Surface', 'Subsea', 'Combined Surface and Subsea'];
    public const SUPPLEMENT_OPTIONS = ['No Supplement Offered', 'Workover'];

    protected function prepareForValidation(): void
    {
        if (! $this->filled('exam_id') && $this->route('exam')) {
            $this->merge(['exam_id' => $this->route('exam')->getKey()]);
        }
        if (! $this->filled('start_mode')) {
            $this->merge(['start_mode' => 'automatic']);
        }
        if ($this->has('class_id')) {
            $this->merge(['class_id' => trim((string) $this->input('class_id'))]);
        }
        if ($this->has('stack_offered')) {
            $this->merge(['stack_offered' => trim((string) $this->input('stack_offered')) ?: null]);
        }
        if (! $this->filled('supplement_offered')) {
            $this->merge(['supplement_offered' => 'No Supplement Offered']);
        } else {
            $this->merge(['supplement_offered' => trim((string) $this->input('supplement_offered'))]);
        }
        if ($this->filled('training_provider_id') && ! $this->filled('training_provider_location_id')) {
            $locations = TrainingProviderLocation::query()
                ->where('training_provider_id', $this->input('training_provider_id'))
                ->where('is_active', true)
                ->pluck('id');
            if ($locations->count() === 1) {
                $this->merge(['training_provider_location_id' => $locations->first()]);
            }
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
            'class_id' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('exam_schedules', 'class_id')->ignore($this->route('schedule')?->getKey()),
            ],
            'group_id' => ['required', 'integer', 'exists:student_groups,id'],
            'stack_offered' => ['nullable', 'string', Rule::in(self::STACK_OPTIONS)],
            'supplement_offered' => ['required', 'string', Rule::in(self::SUPPLEMENT_OPTIONS)],
            'training_provider_id' => ['nullable', 'integer', Rule::exists('training_providers', 'id')],
            'training_provider_location_id' => ['nullable', 'integer', Rule::exists('training_provider_locations', 'id')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'start_mode' => ['required', 'in:automatic,manual'],
            'proctor_id' => ['required', 'integer', Rule::exists('users', 'id'), new ActiveStaffWithRole(Role::PROCTOR, 'Proctor')],
            'instructor_id' => ['required', 'integer', Rule::exists('users', 'id'), new ActiveStaffWithRole(Role::INSTRUCTOR, 'Instructor')],
        ];
    }

    public function messages(): array
    {
        return [
            'class_id.unique' => 'Class ID has already been used. Please enter a unique Class ID.',
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
            if ($this->filled('start_date') && $this->filled('end_date')) {
                $start = $this->input('start_date').' '.($this->input('start_time') ?: '00:00');
                $end = $this->input('end_date').' '.($this->input('end_time') ?: '23:59');
                if ($end < $start) {
                    $validator->errors()->add('end_time', 'The exam end date and time must be after the start date and time.');
                }
            }

            $providerId = $this->integer('training_provider_id');
            $locationId = $this->integer('training_provider_location_id');
            if ($providerId && ! $locationId) {
                $validator->errors()->add('training_provider_location_id', 'Select a location for this training provider.');
            } elseif (! $providerId && $locationId) {
                $validator->errors()->add('training_provider_location_id', 'Select the training provider for this location.');
            } elseif ($providerId && $locationId) {
                $location = TrainingProviderLocation::query()->find($locationId);
                $currentLocationId = $this->route('schedule')?->training_provider_location_id;
                if (! $location || (int) $location->training_provider_id !== $providerId || (! $location->is_active && (int) $currentLocationId !== $locationId)) {
                    $validator->errors()->add('training_provider_location_id', 'Select an active location belonging to the selected training provider.');
                }
            }
        });
    }
}
