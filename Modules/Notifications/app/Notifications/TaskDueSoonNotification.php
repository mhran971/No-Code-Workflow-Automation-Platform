<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Notifications\Enums\NotificationType;
use Modules\Notifications\Notifications\Channels\FcmChannel;
use Modules\Workflows\Models\WorkflowTask;

/**
 * Sent to the assignee when a task is due within 24 hours.
 */
class TaskDueSoonNotification extends Notification implements ShouldQueue
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
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $dueAtStr = $this->task->due_at?->format('Y-m-d H:i');

        $title = __('notifications::notifications.task_due_soon.title', ['title' => $this->task->title]);
        if ($title === 'notifications::notifications.task_due_soon.title') {
            $title = __('workflows::notifications.task_due_soon.title', ['title' => $this->task->title]);
        }

        $body = __('notifications::notifications.task_due_soon.body', ['due_at' => $dueAtStr]);
        if ($body === 'notifications::notifications.task_due_soon.body') {
            $body = __('workflows::notifications.task_due_soon.body', ['due_at' => $dueAtStr]);
        }

        return [
            'type' => NotificationType::TaskDueSoon->value,
            'title' => $title,
            'body' => $body,
            'task_id' => $this->task->id,
            'instance_id' => $this->task->instance_id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toFcm(object $notifiable): array
    {
        $dueAtStr = $this->task->due_at?->format('Y-m-d H:i');

        $title = __('notifications::notifications.task_due_soon.title', ['title' => $this->task->title]);
        if ($title === 'notifications::notifications.task_due_soon.title') {
            $title = __('workflows::notifications.task_due_soon.title', ['title' => $this->task->title]);
        }

        $body = __('notifications::notifications.task_due_soon.body', ['due_at' => $dueAtStr]);
        if ($body === 'notifications::notifications.task_due_soon.body') {
            $body = __('workflows::notifications.task_due_soon.body', ['due_at' => $dueAtStr]);
        }

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => NotificationType::TaskDueSoon->value,
                'task_id' => $this->task->id,
                'instance_id' => $this->task->instance_id,
            ],
        ];
    }
}
