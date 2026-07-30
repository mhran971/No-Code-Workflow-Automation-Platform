<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Workflows\Database\Factories\WorkflowEdgeFactory;

class WorkflowEdge extends Model
{
    protected $fillable = [
        'workflow_id',
        'version_id',
        'source_node_key',
        'source_handle',
        'target_node_key',
        'target_handle',
        'branch_type',
        'condition_expression',
        'is_default_branch',
        'parallel_group_key',
        'join_node_key',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            // keep raw branch_type for backwards compatibility but do not cast to enum
            'is_default_branch' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'version_id');
    }

    public function isConditional(): bool
    {
        return ! empty($this->condition_expression);
    }

    public function isParallel(): bool
    {
        return ! empty($this->join_node_key) || ! empty($this->parallel_group_key);
    }

    public function isForkJoin(): bool
    {
        return $this->isParallel();
    }
}
