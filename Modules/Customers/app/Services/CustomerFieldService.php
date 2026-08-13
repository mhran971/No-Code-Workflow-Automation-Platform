<?php

namespace Modules\Customers\Services;

use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Customers\Models\CustomerField;
use Modules\Customers\Repositories\CustomerFieldRepository;

class CustomerFieldService extends BaseService
{
    protected ?string $repositoryClass = CustomerFieldRepository::class;

    public function __construct(
        protected CustomerFieldRepository $customerFieldRepository,
    ) {}

    public function listForTenant(int $tenantId): Collection
    {
        return $this->customerFieldRepository->allForTenant($tenantId);
    }

    public function create(int $tenantId, array $data): CustomerField
    {
        $this->assertKeyNotReserved($data['key']);

        $data['tenant_id'] = $tenantId;

        return $this->customerFieldRepository->create($data);
    }

    public function update(CustomerField $field, array $data): CustomerField
    {
        if (array_key_exists('key', $data)) {
            $this->assertKeyNotReserved($data['key']);
        }

        return $this->customerFieldRepository->update($field, $data);
    }

    public function delete(CustomerField $field): void
    {
        $this->customerFieldRepository->delete($field);
    }

    protected function assertKeyNotReserved(string $key): void
    {
        if (in_array($key, CustomerService::RESERVED_KEYS, true)) {
            throw ValidationException::withMessages([
                'key' => "'{$key}' is a reserved field name and cannot be used for a custom field.",
            ]);
        }
    }
}
