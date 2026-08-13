<?php

namespace Modules\Workflows\Services;

use Modules\Auth\Models\User;
use Modules\Notifications\Notifications\TaskEscalatedNotification;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Models\WorkflowTask;

class EscalationService
{
    /**
     * Mark an overdue task as escalated and notify the assignee's manager if present.
     */
    public function escalate(WorkflowTask $task): void
    {
        $task->update([
            'status' => 'escalated',
            'escalated_at' => now(),
        ]);

        $manager = $this->resolveManager($task);
        if ($manager !== null) {
            $manager->notify(new TaskEscalatedNotification($task));
        }
    }

    /**
     * Resolve the assigned manager for the given task's assignee.
     */
    public function resolveManager(WorkflowTask $task): ?User
    {
        if ($task->assignee_id === null) {
            return null;
        }

        $membership = TeamMembership::query()
            ->where('user_id', $task->assignee_id)
            ->where('tenant_id', $task->tenant_id)
            ->with(['team.manager'])
            ->first();

        return $membership?->team?->manager;
    }
}
