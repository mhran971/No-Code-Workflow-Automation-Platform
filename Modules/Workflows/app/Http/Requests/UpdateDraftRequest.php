<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\Role;

class UpdateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth('api')->user()?->role, [Role::Manager, Role::BusinessOwner], true);
    }

    public function rules(): array
    {
        return [
            'definition' => ['required', 'array'],
            'expected_draft_revision' => ['required', 'integer', 'min:1'],
            'change_note' => ['nullable', 'string', 'max:1000'],
            'validate_only' => ['sometimes', 'boolean'],
        ];
    }
}
