<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Auth\Enums\Role;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->user()?->role === Role::Manager;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(['blank', 'template', 'ai_confirmed'])],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'template_id' => ['required_if:method,template', 'nullable', 'integer'],
            'definition' => ['required_if:method,ai_confirmed', 'nullable', 'array'],
        ];
    }
}
