<?php

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerLinkingField;

class UpdateCustomerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->tenant_id;
    }

    public function rules(): array
    {
        return [
            'linking_field' => ['required', Rule::enum(CustomerLinkingField::class)],
        ];
    }
}
