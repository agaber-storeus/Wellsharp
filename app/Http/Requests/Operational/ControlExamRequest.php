<?php

namespace App\Http\Requests\Operational;

use App\Enums\ProctorVerificationFailureReason;
use App\Models\TrainingClass;
use App\Services\AuditRecorder;
use Illuminate\Contracts\Validation\Validator;
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
            // A Proctor controls directly with no credential. An Instructor must supply
            // an active Proctor's ID as a dual-control/oversight check.
            'proctor_id' => [Rule::requiredIf(fn (): bool => $this->user()?->hasRole('instructor') === true), 'nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'proctor_id.required' => "Proctor's ID is required.",
            'proctor_id.string' => "Enter a valid Proctor's ID.",
            'proctor_id.max' => "Enter a valid Proctor's ID.",
        ];
    }

    /**
     * A missing Proctor's ID currently fails validation before the
     * controller/action ever runs, so it would otherwise never reach
     * ControlOperationalExamAction's own audit logging. Record the attempt
     * here instead - the validation failure itself (message, status code)
     * is untouched; this only adds a security event alongside it.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->isInstructorMissingProctorId($validator)) {
            app(AuditRecorder::class)->record(
                'class.proctor_verification.failed',
                $this->route('trainingClass'),
                null,
                [
                    'operation' => $this->input('action'),
                    'failure_stage' => ProctorVerificationFailureReason::MissingProctorId->stage(),
                    'failure_reason' => ProctorVerificationFailureReason::MissingProctorId->value,
                ],
                ProctorVerificationFailureReason::MissingProctorId->label(),
                $this->user()?->getKey(),
            );
        }

        parent::failedValidation($validator);
    }

    private function isInstructorMissingProctorId(Validator $validator): bool
    {
        return $this->user()?->hasRole('instructor') === true
            && blank($this->input('proctor_id'))
            && $validator->errors()->has('proctor_id')
            && $this->route('trainingClass') instanceof TrainingClass;
    }
}
