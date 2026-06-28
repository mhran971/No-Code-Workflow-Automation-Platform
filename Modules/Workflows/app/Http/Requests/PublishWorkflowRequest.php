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
        return [];
    }
}
