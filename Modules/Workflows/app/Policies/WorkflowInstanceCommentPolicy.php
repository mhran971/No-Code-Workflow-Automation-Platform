<?php

namespace Modules\Workflows\Policies;

use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Workflows\Models\WorkflowInstanceComment;

class WorkflowInstanceCommentPolicy
{
    public function view(User $user, WorkflowInstanceComment $comment): bool
    {
        $instance = $comment->workflowInstance;

        return $instance !== null && (int) $instance->tenant_id === (int) $user->tenant_id;
    }

    public function delete(User $user, WorkflowInstanceComment $comment): bool
    {
        if ($user->role === Role::BusinessOwner) {
            return true;
        }

        return (int) $comment->user_id === (int) $user->id;
    }
}
