<?php

namespace Modules\Team\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\Role;

class DisableUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth('api')->user();

        return $user !== null && $user->role === Role::BusinessOwner;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reassign_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
