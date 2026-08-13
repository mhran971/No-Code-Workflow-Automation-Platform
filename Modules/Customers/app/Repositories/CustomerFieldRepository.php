<?php

namespace Modules\Customers\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customers\Models\CustomerField;

class CustomerFieldRepository
{
    public function allForTenant(int $tenantId): Collection
    {
        return CustomerField::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->get();
    }

    public function findForTenant(int $id, int $tenantId): ?CustomerField
    {
        return CustomerField::query()
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function create(array $data): CustomerField
    {
        return CustomerField::query()->create($data);
    }

    public function update(CustomerField $field, array $data): CustomerField
    {
        $field->update($data);

        return $field;
    }

    public function delete(CustomerField $field): void
    {
        $field->delete();
    }
}
