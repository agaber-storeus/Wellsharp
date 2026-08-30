<?php

namespace App\Http\Requests\Admin;

use App\Services\SystemLogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'category' => ['nullable', Rule::in(array_keys(SystemLogService::categories()))],
            'action' => ['nullable', 'string', 'max:120'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'actor_role' => ['nullable', Rule::in(array_keys(SystemLogService::roles()))],
            'subject_type' => ['nullable', 'string', 'max:120'],
            'result' => ['nullable', Rule::in(['success', 'failed', 'system'])],
            'correlation_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only([
            'date_from', 'date_to', 'category', 'action', 'actor_id', 'actor_role',
            'subject_type', 'result', 'correlation_id', 'search',
        ]))->map(fn ($value) => is_string($value) ? trim($value) : $value)->all());
    }
}
