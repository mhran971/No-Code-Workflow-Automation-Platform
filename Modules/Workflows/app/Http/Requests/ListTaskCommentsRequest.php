<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListTaskCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}