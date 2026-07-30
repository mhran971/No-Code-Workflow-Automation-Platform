<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Notifications\Enums\NotificationType;
use Modules\Notifications\Notifications\Channels\FcmChannel;
use Modules\Workflows\Models\WorkflowTask;

/**
 * Sent to the assignee when a new WorkflowTask is created.
 */
class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly WorkflowTask $task) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /**
     * Data stored in the `notifications` table (database channel).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = __('notifications::notifications.task_assigned.title', ['title' => $this->task->title]);
        if ($title === 'notifications::notifications.task_assigned.title') {
            $title = __('workflows::notifications.task_assigned.title', ['title' => $this->task->title]);
        }

        $body = __('notifications::notifications.task_assigned.body');
        if ($body === 'notifications::notifications.task_assigned.body') {
            $body = __('workflows::notifications.task_assigned.body');
        }

        return [
            'type' => NotificationType::TaskAssigned->value,
            'title' => $title,
            'body' => $body,
            'task_id' => $this->task->id,
            'instance_id' => $this->task->instance_id,
        ];
    }

    /**
     * Payload for the FCM push notification.
     *
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toFcm(object $notifiable): array
    {
        $title = __('notifications::notifications.task_assigned.title', ['title' => $this->task->title]);
        if ($title === 'notifications::notifications.task_assigned.title') {
            $title = __('workflows::notifications.task_assigned.title', ['title' => $this->task->title]);
        }

        $body = __('notifications::notifications.task_assigned.body');
        if ($body === 'notifications::notifications.task_assigned.body') {
            $body = __('workflows::notifications.task_assigned.body');
        }

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => NotificationType::TaskAssigned->value,
                'task_id' => $this->task->id,
                'instance_id' => $this->task->instance_id,
            ],
        ];
    }
}
