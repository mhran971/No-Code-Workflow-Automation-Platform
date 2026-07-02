<?php

namespace Modules\Workflows\Policies;

use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Workflows\Models\WorkflowInstanceAttachment;

class WorkflowInstanceAttachmentPolicy
{
    public function view(User $user, WorkflowInstanceAttachment $attachment): bool
    {
        $instance = $attachment->workflowInstance;

        return $instance !== null && (int) $instance->tenant_id === (int) $user->tenant_id;
    }

    public function delete(User $user, WorkflowInstanceAttachment $attachment): bool
    {
        if ($user->role === Role::BusinessOwner) {
            return true;
        }

        return (int) $attachment->uploaded_by === (int) $user->id;
    }
}
