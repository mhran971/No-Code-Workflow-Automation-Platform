<?php

namespace Modules\Workflows\Services\Execution\Concerns;

use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Events\NodeRetrying;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\Data\PlanEdge;
use Modules\Workflows\Services\Execution\EventBroadcaster;
use Modules\Workflows\Services\Execution\ExecutionPlan;
use Modules\Workflows\Services\Execution\MergeCoordinator;
use Modules\Workflows\Services\Execution\NodeExecutorRegistry;
use Modules\Workflows\Services\Execution\RetryPolicy;

/**
 * Shared execution-row creation and job dispatch used by WorkflowExecutionEngine,
 * WorkflowDispatcher, and MergeCoordinator.
 *
 * @requires NodeExecutorRegistry  $registry
 * @requires RetryPolicy           $retryPolicy
 * @requires EventBroadcaster      $broadcaster
 */
trait NodeSeeder
{
    /**
     * Create an execution row idempotently. Returns the existing row if the idempotency key
     * already exists (at-least-once queue delivery).
     *
     * @param  array<string, mixed>  $extra  additional attributes (e.g. merge-specific fields)
     */
    protected function createExecutionRow(
        WorkflowInstance $instance,
        string $idempotencyKey,
        string $nodeKey,
        string $nodeType,
        int $attempt,
        ?int $parentExecutionId,
        array $input,
        array $extra = [],
    ): WorkflowNodeExecution {
        return WorkflowNodeExecution::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            array_merge([
                'instance_id' => $instance->id,
                'tenant_id' => $instance->tenant_id,
                'node_key' => $nodeKey,
                'node_type' => $nodeType,
                'status' => NodeExecutionStatus::Pending,
                'attempt' => $attempt,
                'parent_execution_id' => $parentExecutionId,
                'input' => $input,
            ], $extra),
        );
    }

    /**
     * Seed a child execution for the given outgoing edge and dispatch a job.
     * Delegates merge-node arrivals to MergeCoordinator.
     */
    protected function seedNextExecution(
        WorkflowNodeExecution $parent,
        WorkflowInstance $instance,
        PlanEdge $edge,
        ExecutionPlan $plan,
        MergeCoordinator $mergeCoordinator,
    ): void {
        $nodeKey = $edge->target;
        $nodeType = $plan->nodeType($nodeKey) ?? 'unknown';

        if ($plan->isMergeNode($nodeKey)) {
            $mergeCoordinator->arrive($parent, $instance, $nodeKey, $nodeType, $plan);

            return;
        }

        $idempotencyKey = hash('sha256', $parent->idempotency_key.':'.$nodeKey.':1');

        $child = $this->createExecutionRow(
            $instance,
            $idempotencyKey,
            $nodeKey,
            $nodeType,
            1,
            $parent->id,
            $parent->output ?? [],
        );

        if ($child->wasRecentlyCreated) {
            $category = $this->resolveCategory($nodeType);
            ExecuteNodeJob::dispatch($child->id, $category->value)
                ->onQueue($this->queueFor($category));
        }
    }

    /**
     * Create a retry execution row for a failed node, with a delay.
     */
    protected function scheduleRetryExecution(
        WorkflowNodeExecution $failed,
        WorkflowInstance $instance,
    ): void {
        $nextAttempt = $failed->attempt + 1;
        $idempotencyKey = hash('sha256', $instance->id.':'.$failed->node_key.':'.$nextAttempt);

        $retry = $this->createExecutionRow(
            $instance,
            $idempotencyKey,
            $failed->node_key,
            $failed->node_type,
            $nextAttempt,
            $failed->parent_execution_id,
            $failed->input ?? [],
        );

        if ($retry->wasRecentlyCreated) {
            $this->broadcaster->broadcast(new NodeRetrying($instance, $failed, $retry));

            $delay = $this->retryPolicy->delaySeconds($nextAttempt);
            $category = $this->resolveCategory($failed->node_type);
            ExecuteNodeJob::dispatch($retry->id, $category->value)
                ->onQueue($this->queueFor($category))
                ->delay(now()->addSeconds($delay));
        }
    }
}
