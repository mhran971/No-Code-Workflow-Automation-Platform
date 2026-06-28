<?php

return [
    'name' => 'Workflows',

    /*
    |--------------------------------------------------------------------------
    | Execution engine
    |--------------------------------------------------------------------------
    | Tunables for the workflow execution engine. See
    | Modules/Workflows/docs/execution-engine/ for the design behind these.
    */
    'execution' => [
        'queues' => [
            'control' => env('WORKFLOW_QUEUE_CONTROL', 'workflow-control'),
            'actions' => env('WORKFLOW_QUEUE_ACTIONS', 'workflow-actions'),
        ],

        // Per-attempt wall-clock seconds, by execution category.
        'timeouts' => [
            'logic' => 30,
            'action' => 60,
            'ai' => 120,
        ],

        // Default retry policy; per-node config may override.
        'retry' => [
            'max_attempts' => 3,
            'base_delay' => 5,   // seconds
            'max_delay' => 300,  // seconds
            'strategy' => 'exponential',
        ],

        // Global instance deadline backstop, in minutes (default 30 days).
        'max_instance_duration' => 30 * 24 * 60,

        // Per-tenant backpressure / admission control.
        'admission' => [
            'max_concurrent_instances_per_tenant' => 20,
            'max_admitted_per_minute_per_tenant' => null, // optional token bucket
        ],

        'task' => [
            'default_due_within_hours' => 24,
            'expire_on_overdue' => false, // keep waiting by default
        ],
    ],
];
