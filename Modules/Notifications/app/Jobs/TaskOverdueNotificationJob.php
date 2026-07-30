<?php

namespace Modules\Notifications\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notifications\Notifications\TaskOverdueNotification;
use Modules\Workflows\Models\WorkflowTask;

/**
 * Scheduled hourly — finds open tasks whose due_at has already
 * passed and sends a one-time "overdue" notification to the
 * assignee (both in-app and push).
 *
 * Deduplication: checks that no TaskOverdueNotification for the
 * same task_id has already been sent to the user.
 */
class TaskOverdueNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        WorkflowTask::query()
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->whereNotNull('assignee_id')
            ->where('due_at', '<', now())
            ->with('assignee')
            ->each(function (WorkflowTask $task): void {
                if (! $task->assignee) {
                    return;
                }

                // Skip if we already sent an overdue notification for this task.
                $alreadySent = $task->assignee
                    ->notifications()
                    ->where(function ($query) {
                        $query->where('type', TaskOverdueNotification::class)
                            ->orWhere('type', 'Modules\Workflows\Notifications\TaskOverdueNotification');
                    })
                    ->whereJsonContains('data->task_id', $task->id)
                    ->exists();

                if (! $alreadySent) {
                    $task->assignee->notify(new TaskOverdueNotification($task));
                }
            });
    }
}
