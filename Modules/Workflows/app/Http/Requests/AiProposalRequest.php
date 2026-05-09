<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\Role;

class AiProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth('api')->user()?->role, [Role::Manager, Role::BusinessOwner], true);
    }

    public function rules(): array
    {
        return [
            'goal' => ['required', 'string', 'min:10', 'max:4000'],
            'team_id' => ['nullable', 'integer'],
            'constraints' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
        ];
    }
}
