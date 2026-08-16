<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RollbackWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in the service layer.
    }

    public function rules(): array
    {
        return [
            'release_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
