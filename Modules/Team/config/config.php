<?php

return [
    'name' => 'Team',

    'task_reassignment' => [
        'tables' => [
            [
                'table' => 'tasks',
                'tenant_column' => 'tenant_id',
                'assignee_column' => 'assignee_id',
                'status_column' => 'status',
                'active_statuses' => ['pending', 'in_progress', 'open'],
            ],
            [
                'table' => 'workflow_tasks',
                'tenant_column' => 'tenant_id',
                'assignee_column' => 'assigned_user_id',
                'status_column' => 'status',
                'active_statuses' => ['pending', 'in_progress', 'open'],
            ],
            [
                'table' => 'instances',
                'tenant_column' => 'tenant_id',
                'assignee_column' => 'assigned_user_id',
                'status_column' => 'status',
                'active_statuses' => ['active', 'running'],
            ],
        ],
    ],

    'deletion_guard' => [
        'history_tables' => [
            [
                'table' => 'instances',
                'tenant_column' => 'tenant_id',
                'user_column' => 'resolved_by_user_id',
                'status_column' => 'status',
                'history_statuses' => ['completed', 'closed', 'resolved'],
                'label' => 'resolved/completed instances',
            ],
            [
                'table' => 'comments',
                'tenant_column' => 'tenant_id',
                'user_column' => 'user_id',
                'label' => 'past comments',
            ],
            [
                'table' => 'notes',
                'tenant_column' => 'tenant_id',
                'user_column' => 'user_id',
                'label' => 'past notes',
            ],
        ],
    ],
];
