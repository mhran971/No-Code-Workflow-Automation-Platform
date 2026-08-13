<?php

namespace Modules\Customers\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Customers\Models\Customer;

class CustomerRepository
{
    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findForTenant(int $id, int $tenantId): ?Customer
    {
        return Customer::query()
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function create(array $data): Customer
    {
        return Customer::query()->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }
}
