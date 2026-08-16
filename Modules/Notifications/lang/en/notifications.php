<?php

return [
    'task_assigned' => [
        'title' => 'New task: :title',
        'body' => 'You have been assigned a new task.',
    ],
    'task_due_soon' => [
        'title' => 'Task due soon: :title',
        'body' => 'Deadline: :due_at',
    ],
    'task_overdue' => [
        'title' => 'Overdue task: :title',
        'body' => 'This task has passed its deadline.',
    ],
    'task_escalated' => [
        'title' => 'Task Escalated: :title',
        'body' => 'A task assigned to :assignee has passed its due date and was escalated to you.',
    ],
];
