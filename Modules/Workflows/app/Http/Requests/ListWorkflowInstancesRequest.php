<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Workflows\Enums\WorkflowInstanceStatus;

class ListWorkflowInstancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(array_map(
                static fn (WorkflowInstanceStatus $status) => $status->value,
                WorkflowInstanceStatus::cases()
            ))],
            'started_from' => ['sometimes', 'date_format:Y-m-d'],
            'started_to' => ['sometimes', 'date_format:Y-m-d'],
            'finished_from' => ['sometimes', 'date_format:Y-m-d'],
            'finished_to' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
