<?php

namespace Modules\Workflows\Observers;

use Modules\Notifications\Notifications\TaskAssignedNotification;
use Modules\Workflows\Models\WorkflowTask;

/**
 * Observes WorkflowTask model events.
 *
 * When a task is created with an assignee, a push + in-app
 * notification is sent automatically.
 */
class WorkflowTaskObserver
{
    /**
     * Handle the WorkflowTask "created" event.
     */
    public function created(WorkflowTask $task): void
    {
        if ($task->assignee_id === null) {
            return;
        }

        $task->loadMissing('assignee');

        if ($task->assignee) {
            $task->assignee->notify(new TaskAssignedNotification($task));
        }
    }
}
