<?php

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerFieldType;
use Modules\Customers\Services\CustomerService;

class StoreCustomerFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->tenant_id;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()?->tenant_id;

        return [
            'key' => [
                'required', 'string', 'max:255',
                Rule::notIn(CustomerService::RESERVED_KEYS),
                Rule::unique('customer_fields', 'key')->where('tenant_id', $tenantId),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CustomerFieldType::class)],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            'default_value' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
