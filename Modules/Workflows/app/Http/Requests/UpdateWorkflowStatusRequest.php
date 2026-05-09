<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Auth\Enums\Role;
use Modules\Workflows\Enums\WorkflowStatus;

class UpdateWorkflowStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth('api')->user()?->role, [Role::Manager, Role::BusinessOwner], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([WorkflowStatus::Active->value, WorkflowStatus::Disabled->value])],
        ];
    }
}
