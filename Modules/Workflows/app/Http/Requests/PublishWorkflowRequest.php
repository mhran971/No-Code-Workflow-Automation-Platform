<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\Role;

class PublishWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth('api')->user()?->role, [Role::Manager, Role::BusinessOwner], true);
    }

    public function rules(): array
    {
        return [
            'expected_draft_revision' => ['required', 'integer', 'min:1'],
            'release_note' => ['nullable', 'string', 'max:2000'],
            'version_label' => ['nullable', 'string', 'regex:/^v[0-9]+\\.[0-9]+\\.[0-9]+$/'],
        ];
    }
}
