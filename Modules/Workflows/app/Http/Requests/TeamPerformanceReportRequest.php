<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamPerformanceReportRequest extends FormRequest
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
            'member_id' => ['sometimes', 'integer', 'exists:users,id'],
            'team_id' => ['sometimes', 'integer', 'exists:teams,id'],
        ];
    }
}
