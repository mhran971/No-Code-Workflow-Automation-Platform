<?php

namespace Modules\Workflows\Services\Execution\Admission;

use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Models\WorkflowInstance;

/**
 * Per-tenant backpressure gate. When a tenant is at or above the concurrent-instance cap,
 * new instances are created in `pending` status and promoted by AdmitPendingInstancesCommand
 * as capacity frees up.
 */
class InstanceAdmissionService
{
    public function canAdmit(int $tenantId): bool
    {
        $cap = (int) config('workflows.execution.admission.max_concurrent_instances_per_tenant', 20);

        $running = WorkflowInstance::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                WorkflowInstanceStatus::Running->value,
                WorkflowInstanceStatus::Waiting->value,
            ])
            ->count();

        return $running < $cap;
    }
}
