<?php

namespace Modules\Notifications\Enums;

/**
 * Notification type identifiers stored in the `data` JSON
 * of the Laravel notifications table.
 */
enum NotificationType: string
{
    case TaskAssigned = 'task_assigned';
    case TaskDueSoon = 'task_due_soon';
    case TaskOverdue = 'task_overdue';
    case TaskEscalated = 'task_escalated';
}
