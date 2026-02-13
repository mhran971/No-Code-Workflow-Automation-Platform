<?php

namespace Modules\Auth\Repositories;

use Modules\Auth\Models\Tenant;

class TenantRepository
{
    /**
     * Create a new tenant.
     */
    public function create(array $data): Tenant
    {
        return Tenant::create($data);
    }
}
