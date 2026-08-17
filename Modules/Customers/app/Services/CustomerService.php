<?php

namespace Modules\Customers\Services;

use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Customers\Models\Customer;
use Modules\Customers\Repositories\CustomerFieldRepository;
use Modules\Customers\Repositories\CustomerRepository;

class CustomerService extends BaseService
{
    protected ?string $repositoryClass = CustomerRepository::class;

    /** Fixed columns a CustomerField key must never collide with. */
    public const RESERVED_KEYS = [
        'id', 'tenant_id', 'email', 'phone', 'first_name', 'last_name',
        'custom_field_values', 'is_active', 'created_at', 'updated_at',
    ];

    public function __construct(
        protected CustomerRepository $customerRepository,
        protected CustomerFieldRepository $customerFieldRepository,
    ) {}

    public function listForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        $customers = $this->customerRepository->paginateForTenant($tenantId, $perPage);
        $fields = $this->customerFieldRepository->allForTenant($tenantId);

        $customers->getCollection()->each(
            fn (Customer $customer) => $customer->custom_field_values = $this->fillCustomFieldValues($fields, $customer->custom_field_values)
        );

        return $customers;
    }

    public function getForTenant(int $id, int $tenantId): ?Customer
    {
        $customer = $this->customerRepository->findForTenant($id, $tenantId);

        if ($customer) {
            $customer->custom_field_values = $this->fillCustomFieldValues(
                $this->customerFieldRepository->allForTenant($tenantId),
                $customer->custom_field_values
            );
        }

        return $customer;
    }

    public function create(int $tenantId, array $data): Customer
    {
        $data['tenant_id'] = $tenantId;
        $fields = $this->customerFieldRepository->allForTenant($tenantId);
        $data['custom_field_values'] = $this->validateCustomFieldValues($fields, $data['custom_field_values'] ?? []);

        $customer = $this->customerRepository->create($data);
        $customer->custom_field_values = $this->fillCustomFieldValues($fields, $customer->custom_field_values);

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $fields = $this->customerFieldRepository->allForTenant((int) $customer->tenant_id);

        if (array_key_exists('custom_field_values', $data)) {
            $data['custom_field_values'] = $this->validateCustomFieldValues(
                $fields,
                array_merge($customer->custom_field_values ?? [], $data['custom_field_values'])
            );
        }

        $customer = $this->customerRepository->update($customer, $data);
        $customer->custom_field_values = $this->fillCustomFieldValues($fields, $customer->custom_field_values);

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        $this->customerRepository->delete($customer);
    }

    /**
     * Reject unknown keys and enforce required custom fields. Values are otherwise stored
     * as-authored — type coercion per CustomerFieldType is left to the (out-of-scope) frontend
     * form renderer, matching how NodeConfigField values are handled on the Workflows side.
     */
    protected function validateCustomFieldValues(Collection $fields, array $values): array
    {
        $knownKeys = $fields->pluck('key')->all();

        $unknown = array_diff(array_keys($values), $knownKeys);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'custom_field_values' => 'Unknown custom field(s): '.implode(', ', $unknown).'.',
            ]);
        }

        $missing = [];
        foreach ($fields as $field) {
            if ($field->is_required && ($values[$field->key] ?? null) === null) {
                $missing[] = $field->key;
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'custom_field_values' => 'Missing required custom field(s): '.implode(', ', $missing).'.',
            ]);
        }

        return $values;
    }

    /**
     * Ensures every currently-configured custom field is present in the returned values,
     * defaulting to the field's default_value (or null) when the customer hasn't filled it in.
     */
    protected function fillCustomFieldValues(Collection $fields, ?array $values): array
    {
        $values ??= [];

        $filled = [];
        foreach ($fields as $field) {
            $filled[$field->key] = $values[$field->key] ?? $field->default_value ?? null;
        }

        return $filled;
    }
}
