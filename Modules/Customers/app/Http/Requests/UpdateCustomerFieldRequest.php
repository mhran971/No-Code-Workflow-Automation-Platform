<?php

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerFieldType;
use Modules\Customers\Services\CustomerService;

class UpdateCustomerFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->tenant_id;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()?->tenant_id;
        $fieldId = $this->route('customerField')?->id ?? $this->route('customer_field');

        return [
            'key' => [
                'sometimes', 'string', 'max:255',
                Rule::notIn(CustomerService::RESERVED_KEYS),
                Rule::unique('customer_fields', 'key')->where('tenant_id', $tenantId)->ignore($fieldId),
            ],
            'label' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(CustomerFieldType::class)],
            'is_required' => ['sometimes', 'boolean'],
            'options' => ['sometimes', 'nullable', 'array'],
            'default_value' => ['sometimes'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
