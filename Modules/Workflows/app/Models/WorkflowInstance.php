<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\Tenant;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;

class WorkflowInstance extends Model
{
    protected $fillable = [
        'workflow_id',
        'workflow_version_id',
        'parent_instance_id',
        'tenant_id',
        'status',
        'trigger_type',
        'correlation_id',
        'payload',
        'context',
        'error',
        'paused_reason',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowInstanceStatus::class,
            'trigger_type' => TriggerType::class,
            'payload' => 'array',
            'context' => 'array',
            'error' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return $this->status instanceof WorkflowInstanceStatus && $this->status->isTerminal();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_instance_id');
    }

    public function nodeExecutions(): HasMany
    {
        return $this->hasMany(WorkflowNodeExecution::class, 'instance_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class, 'instance_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkflowEvent::class, 'instance_id');
    }
}
