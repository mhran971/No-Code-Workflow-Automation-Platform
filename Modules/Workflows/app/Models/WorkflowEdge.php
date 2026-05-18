<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Workflows\Enums\EdgeBranchType;
use Modules\Workflows\Enums\ParallelStrategy;

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
        'parallel_strategy',
        'join_node_key',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'branch_type'       => EdgeBranchType::class,
            'parallel_strategy' => ParallelStrategy::class,
            'is_default_branch' => 'boolean',
            'sort_order'        => 'integer',
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
        return $this->branch_type === EdgeBranchType::Conditional;
    }

    public function isParallel(): bool
    {
        return $this->branch_type === EdgeBranchType::Parallel;
    }

    public function isForkJoin(): bool
    {
        return $this->isParallel()
            && $this->parallel_strategy === ParallelStrategy::ForkJoin;
    }
}
