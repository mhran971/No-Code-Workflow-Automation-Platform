<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowAnalyticsReportRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::in(['completed', 'failed', 'running', 'waiting', 'paused', 'cancelled', 'pending'])],
            'team_id' => ['sometimes', 'integer', 'exists:teams,id'],
        ];
    }
}
