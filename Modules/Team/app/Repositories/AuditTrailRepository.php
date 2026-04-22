<?php

namespace Modules\Team\Repositories;

use Modules\Team\Models\AuditTrail;

class AuditTrailRepository
{
    /**
     * Store a new audit trail record.
     */
    public function create(array $data): AuditTrail
    {
        return AuditTrail::create($data);
    }
}
