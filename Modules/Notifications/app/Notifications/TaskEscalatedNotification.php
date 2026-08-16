<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Notifications\Enums\NotificationType;
use Modules\Notifications\Notifications\Channels\FcmChannel;
use Modules\Workflows\Models\WorkflowTask;

/**
 * Sent to the manager when an assigned task passes its due date without completion.
 */
class TaskEscalatedNotification extends Notification implements ShouldQueue
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
        $assigneeName = $this->task->assignee?->name ?? __('Unknown');

        $title = __('notifications::notifications.task_escalated.title', [
            'title' => $this->task->title,
            'assignee' => $assigneeName,
        ]);
        if ($title === 'notifications::notifications.task_escalated.title') {
            $title = __('workflows::notifications.task_escalated.title', [
                'title' => $this->task->title,
                'assignee' => $assigneeName,
            ]);
        }

        $body = __('notifications::notifications.task_escalated.body', [
            'title' => $this->task->title,
            'assignee' => $assigneeName,
        ]);
        if ($body === 'notifications::notifications.task_escalated.body') {
            $body = __('workflows::notifications.task_escalated.body', [
                'title' => $this->task->title,
                'assignee' => $assigneeName,
            ]);
        }

        return [
            'type' => NotificationType::TaskEscalated->value,
            'title' => $title,
            'body' => $body,
            'task_id' => $this->task->id,
            'instance_id' => $this->task->instance_id,
            'assignee_id' => $this->task->assignee_id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toFcm(object $notifiable): array
    {
        $assigneeName = $this->task->assignee?->name ?? __('Unknown');

        $title = __('notifications::notifications.task_escalated.title', [
            'title' => $this->task->title,
            'assignee' => $assigneeName,
        ]);
        if ($title === 'notifications::notifications.task_escalated.title') {
            $title = __('workflows::notifications.task_escalated.title', [
                'title' => $this->task->title,
                'assignee' => $assigneeName,
            ]);
        }

        $body = __('notifications::notifications.task_escalated.body', [
            'title' => $this->task->title,
            'assignee' => $assigneeName,
        ]);
        if ($body === 'notifications::notifications.task_escalated.body') {
            $body = __('workflows::notifications.task_escalated.body', [
                'title' => $this->task->title,
                'assignee' => $assigneeName,
            ]);
        }

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => NotificationType::TaskEscalated->value,
                'task_id' => $this->task->id,
                'instance_id' => $this->task->instance_id,
                'assignee_id' => $this->task->assignee_id,
            ],
        ];
    }
}
