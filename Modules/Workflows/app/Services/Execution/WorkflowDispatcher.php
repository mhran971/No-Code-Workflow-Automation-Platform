<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\ChildInstanceEventForwarded;
use Modules\Workflows\Events\InstanceStarted;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\Execution\Admission\InstanceAdmissionService;

/**
 * Creates a new workflow instance, seeds the trigger-node execution, and dispatches the first job.
 *
 * If the tenant is at its concurrent-instance cap the instance is created in `pending` status
 * and the job is withheld; AdmitPendingInstancesCommand will promote it when capacity frees.
 *
 * The entire bootstrap (instance row + first execution row + optional first job) runs inside a
 * single DB transaction so no worker can observe a partially-constructed instance.
 */
class WorkflowDispatcher
{
    public function __construct(
        protected ExecutionPlanCompiler $compiler,
        protected NodeExecutorRegistry $registry,
        protected InstanceAdmissionService $admission,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  trigger payload (webhook body, form fields, etc.)
     * @param  array<string, mixed>|null  $definition  optional raw definition (bypasses version lookup for dynamic-flow segments)
     *
     * @throws ValidationException if the workflow is not published or not triggerable
     */
    public function dispatch(
        Workflow $workflow,
        TriggerType $triggerType,
        array $payload = [],
        ?string $correlationId = null,
        ?int $parentInstanceId = null,
        ?int $parentExecutionId = null,
        ?array $definition = null,
    ): WorkflowInstance {
        $version = null;

        if ($definition !== null) {
            $plan = $this->compiler->compile($definition);
        } else {
            $version = $this->resolveVersion($workflow);
            $plan = $this->compiler->compileVersion($version);
        }

        $triggerNodeKey = $plan->triggerNodeKey();
        if ($triggerNodeKey === null) {
            throw ValidationException::withMessages([
                'workflow' => 'Workflow has no trigger node — publish a definition with a trigger first.',
            ]);
        }

        $admitted = $this->admission->canAdmit((int) $workflow->tenant_id);

        return DB::transaction(function () use (
            $workflow, $version, $plan, $triggerType, $payload, $correlationId, $triggerNodeKey, $admitted, $parentInstanceId, $parentExecutionId
        ): WorkflowInstance {
            $instanceStatus = $admitted ? WorkflowInstanceStatus::Running : WorkflowInstanceStatus::Pending;

            $instance = WorkflowInstance::query()->create([
                'workflow_id' => $workflow->id,
                'workflow_version_id' => $version?->id,
                'parent_instance_id' => $parentInstanceId,
                'parent_execution_id' => $parentExecutionId,
                'tenant_id' => $workflow->tenant_id,
                'status' => $instanceStatus,
                'trigger_type' => $triggerType,
                'correlation_id' => $correlationId,
                'payload' => $payload,
                'context' => [],
                'started_at' => $admitted ? now() : null,
            ]);

            $workflow->increment('total_runs');
            if ($admitted) {
                $workflow->increment('active_instances');
            }

            $triggerNodeType = $plan->nodeType($triggerNodeKey) ?? 'unknown';
            $idempotencyKey = hash('sha256', $instance->id.':'.$triggerNodeKey.':1');
            $category = $this->resolveCategory($triggerNodeType);

            $execution = WorkflowNodeExecution::query()->create([
                'instance_id' => $instance->id,
                'tenant_id' => $workflow->tenant_id,
                'node_key' => $triggerNodeKey,
                'node_type' => $triggerNodeType,
                'status' => NodeExecutionStatus::Pending,
                'attempt' => 1,
                'idempotency_key' => $idempotencyKey,
                'input' => $payload,
            ]);

            // Only dispatch the first job when admitted; AdmitPendingInstancesCommand handles the rest.
            if ($admitted) {
                ExecuteNodeJob::dispatch($execution->id, $category->value)
                    ->onQueue($this->queueFor($category));
            }

            DB::afterCommit(function () use ($instance) {
                $startedEvent = new InstanceStarted($instance);
                Event::dispatch($startedEvent);

                // Forward to parent instance channel if this is a child
                if ($instance->parent_instance_id) {
                    $parentInstance = WorkflowInstance::find($instance->parent_instance_id);
                    if ($parentInstance) {
                        Log::info('workflow.child_event.forwarding_instance_started', [
                            'child_instance_id' => $instance->id,
                            'parent_instance_id' => $parentInstance->id,
                        ]);
                        Event::dispatch(new ChildInstanceEventForwarded(
                            $instance,
                            $parentInstance,
                            $startedEvent->broadcastAs(),
                            $startedEvent->broadcastWith(),
                        ));
                    }
                }
            });

            return $instance;
        });
    }

    protected function resolveVersion(Workflow $workflow): WorkflowVersion
    {
        if ($workflow->current_version_id === null) {
            throw ValidationException::withMessages([
                'workflow' => 'Workflow must be published before it can be triggered.',
            ]);
        }

        return WorkflowVersion::query()->findOrFail($workflow->current_version_id);
    }

    protected function resolveCategory(string $nodeType): NodeCategory
    {
        if ($this->registry->has($nodeType)) {
            return $this->registry->for($nodeType)->category();
        }

        return NodeCategory::Logic;
    }

    protected function queueFor(NodeCategory $category): string
    {
        return $category === NodeCategory::Action || $category === NodeCategory::Ai
            ? (string) config('workflows.execution.queues.actions', 'workflow-actions')
            : (string) config('workflows.execution.queues.control', 'workflow-control');
    }
}
