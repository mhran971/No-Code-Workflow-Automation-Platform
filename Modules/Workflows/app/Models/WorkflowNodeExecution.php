<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Auth\Models\Tenant;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WaitType;

/**
 * The unified runtime-state record. A non-terminal row is an active execution token; `merge` rows hold
 * join arrival counters; `waiting` rows hold their wake-up time.
 */
class WorkflowNodeExecution extends Model
{
    protected $fillable = [
        'instance_id',
        'tenant_id',
        'node_key',
        'node_type',
        'status',
        'attempt',
        'idempotency_key',
        'input',
        'output',
        'error',
        'parent_execution_id',
        'fork_group',
        'expected_count',
        'arrived_count',
        'wait_until',
        'wait_type',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NodeExecutionStatus::class,
            'wait_type' => WaitType::class,
            'input' => 'array',
            'output' => 'array',
            'error' => 'array',
            'attempt' => 'integer',
            'expected_count' => 'integer',
            'arrived_count' => 'integer',
            'wait_until' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_execution_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_execution_id');
    }

    public function task(): HasOne
    {
        return $this->hasOne(WorkflowTask::class, 'execution_id');
    }

    public function isRunnable(): bool
    {
        return $this->status->isRunnable();
    }
}
