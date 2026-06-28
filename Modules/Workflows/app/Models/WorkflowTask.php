<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;

/**
 * Human-facing projection of a parked `task-node`. The execution it points at is the source of truth for
 * resume/timeout; this row powers the assignee inbox and stores the response.
 */
class WorkflowTask extends Model
{
    protected $fillable = [
        'instance_id',
        'execution_id',
        'tenant_id',
        'node_key',
        'assignee_id',
        'title',
        'description',
        'input_schema',
        'status',
        'due_at',
        'response',
        'completed_by_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'response' => 'array',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowNodeExecution::class, 'execution_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }
}
