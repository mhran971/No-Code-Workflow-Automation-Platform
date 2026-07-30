<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Workflows\Enums\DynamicFlowStatus;

class WorkflowDynamicFlow extends Model
{
    protected $fillable = [
        'tenant_id',
        'instance_id',
        'execution_id',
        'node_key',
        'status',
        'definition',
        'created_by_id',
        'child_instance_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DynamicFlowStatus::class,
            'definition' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowNodeExecution::class, 'execution_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function childInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'child_instance_id');
    }
}
