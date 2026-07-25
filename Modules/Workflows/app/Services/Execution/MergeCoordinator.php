<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WaitType;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\ExecutionPlan;

class MergeCoordinator
{
    public function __construct(
        protected string $controlQueue,
    ) {}

    /**
     * Called (inside the result-commit transaction) when a branch token edges into a merge node.
     *
     * Strategy:
     *  - firstOrCreate the single shared merge row per (instance, merge_node).
     *  - lockForUpdate + increment arrived_count.
     *  - For parallel (merge-and): dispatch when arrived == expected.
     *  - For conditional (merge-or): dispatch on first arrival; skip if already terminal.
     *  - Merge timeout is seeded via wait_until/wait_type; ScanWorkflowTimersCommand wakes it up.
     */
    public function arrive(
        WorkflowNodeExecution $parentExecution,
        WorkflowInstance $instance,
        string $mergeKey,
        string $mergeType,
        ExecutionPlan $plan,
    ) : void
    {
        $joinSpec = $plan->joinFor($mergeKey);
        if ($joinSpec === null) {
            return;
        }

        $mergeIdempotencyKey = hash('sha256', $instance->id.':'.$mergeKey.':merge:1');

        // Ensure the single shared merge coordination row exists.
        $merge = WorkflowNodeExecution::firstOrCreate(
            ['idempotency_key' => $mergeIdempotencyKey],
            [
                'instance_id' => $instance->id,
                'tenant_id' => $instance->tenant_id,
                'node_key' => $mergeKey,
                'node_type' => $mergeType,
                'status' => NodeExecutionStatus::Waiting,
                'attempt' => 1,
                'expected_count' => $joinSpec->expectedCount,
                'arrived_count' => 0,
                'input' => $parentExecution->output ?? [],
                'wait_type' => $joinSpec->timeoutSeconds ? WaitType::MergeTimeout : null,
                'wait_until' => $joinSpec->timeoutSeconds ? now()->addSeconds($joinSpec->timeoutSeconds) : null,
            ],
        );

        // Lock the merge row; another branch may be arriving concurrently.
        $merge = WorkflowNodeExecution::query()->lockForUpdate()->findOrFail($merge->id);

        // Conditional merge: first branch already won — this arrival is a no-op.
        if ($merge->status->isTerminal()) {
            return;
        }

        // Increment: safe under lockForUpdate (no other transaction holds this row).
        $arrivedAfterIncrement = $merge->arrived_count + 1;
        DB::table('workflow_node_executions')
            ->where('id', $merge->id)
            ->increment('arrived_count');

        $shouldProceed = $joinSpec->isParallel()
            ? ($arrivedAfterIncrement >= $joinSpec->expectedCount)
            : ($arrivedAfterIncrement === 1);

        if ($shouldProceed) {
            $merge->update(['status' => NodeExecutionStatus::Pending]);
            ExecuteNodeJob::dispatch($merge->id, NodeCategory::Logic->value)
                ->onQueue($this->controlQueue);
        }
    }
}
