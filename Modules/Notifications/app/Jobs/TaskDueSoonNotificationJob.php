<?php

namespace Modules\Notifications\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notifications\Notifications\TaskDueSoonNotification;
use Modules\Workflows\Models\WorkflowTask;

/**
 * Scheduled hourly — finds open tasks whose due_at falls within
 * the next 24 hours and sends a one-time "due soon" notification
 * to the assignee (both in-app and push).
 *
 * Deduplication: checks that no TaskDueSoonNotification for the
 * same task_id has already been sent to the user.
 */
class TaskDueSoonNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        WorkflowTask::query()
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->whereNotNull('assignee_id')
            ->whereBetween('due_at', [now(), now()->addHours(24)])
            ->with('assignee')
            ->each(function (WorkflowTask $task): void {
                if (! $task->assignee) {
                    return;
                }

                // Skip if we already sent a due-soon notification for this task.
                $alreadySent = $task->assignee
                    ->notifications()
                    ->where(function ($query) {
                        $query->where('type', TaskDueSoonNotification::class)
                            ->orWhere('type', 'Modules\Workflows\Notifications\TaskDueSoonNotification');
                    })
                    ->whereJsonContains('data->task_id', $task->id)
                    ->exists();

                if (! $alreadySent) {
                    $task->assignee->notify(new TaskDueSoonNotification($task));
                }
            });
    }
}
