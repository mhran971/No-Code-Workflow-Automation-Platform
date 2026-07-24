<?php

namespace Modules\Workflows\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Modules\Auth\Models\User;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowTask;

class WorkflowInstanceContextService
{
    public function resolveFromTask(WorkflowTask $task, User $actor): WorkflowInstance
    {
        $task->loadMissing('instance');

        $instance = $task->instance;

        if (! $instance) {
            throw new AuthorizationException('Workflow instance not found.');
        }

        if ((int) $instance->tenant_id !== (int) $actor->tenant_id) {
            throw new AuthorizationException('You do not have access to this workflow instance.');
        }

        return $instance;
    }
}
