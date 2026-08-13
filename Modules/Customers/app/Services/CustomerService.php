<?php

namespace Modules\Customers\Services;

use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        return $this->customerRepository->paginateForTenant($tenantId, $perPage);
    }

    public function getForTenant(int $id, int $tenantId): ?Customer
    {
        return $this->customerRepository->findForTenant($id, $tenantId);
    }

    public function create(int $tenantId, array $data): Customer
    {
        $data['tenant_id'] = $tenantId;
        $data['custom_field_values'] = $this->validateCustomFieldValues($tenantId, $data['custom_field_values'] ?? []);

        return $this->customerRepository->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        if (array_key_exists('custom_field_values', $data)) {
            $data['custom_field_values'] = $this->validateCustomFieldValues(
                (int) $customer->tenant_id,
                array_merge($customer->custom_field_values ?? [], $data['custom_field_values'])
            );
        }

        return $this->customerRepository->update($customer, $data);
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
    protected function validateCustomFieldValues(int $tenantId, array $values): array
    {
        $fields = $this->customerFieldRepository->allForTenant($tenantId);
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
}
