<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkflowRecommendationsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'date_from' => ['sometimes', 'date', 'before_or_equal:today'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'workflow_id' => ['sometimes', 'integer', 'exists:workflows,id'],
            'team_id' => ['sometimes', 'integer', 'exists:teams,id'],
            'failure_threshold' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'cancellation_threshold' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'min_executions' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
