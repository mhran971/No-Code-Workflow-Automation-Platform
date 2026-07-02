<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [];
    }
}