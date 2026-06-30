<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'sort' => ['sometimes', Rule::in(['due_asc', 'due_desc', 'created_asc'])],
            'status' => ['sometimes', Rule::in(['open', 'completed', 'expired', 'cancelled'])],
            'search' => ['sometimes', 'string', 'max:255'],
            'assignee_id' => ['sometimes', 'integer', 'exists:users,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
