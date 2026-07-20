<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Http\Requests\ListWorkflowInstancesRequest;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\WorkflowRuntime;
use Modules\Workflows\Services\WorkflowManagementService;

/**
 * Operator view of running instances: list, inspect, cancel, and retry-from-node.
 */
class WorkflowInstanceController extends Controller
{
    public function __construct(
        protected WorkflowRuntime $runtime,
        protected WorkflowManagementService $workflowManagementService,
    ) {}

    /**
     * List instances for a given workflow (most recent first).
     */
    public function index(ListWorkflowInstancesRequest $request, Workflow $workflow): JsonResponse
    {
        $validated = $request->validated();
        $workflow = $this->workflowManagementService->getVisibleWorkflow($this->actor(), $workflow);

        $query = WorkflowInstance::query()
            ->where('workflow_id', $workflow->id)
            ->where('tenant_id', $this->actor()->tenant_id);

        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }

        if (array_key_exists('started_from', $validated)) {
            $query->whereDate('started_at', '>=', $validated['started_from']);
        }

        if (array_key_exists('started_to', $validated)) {
            $query->whereDate('started_at', '<=', $validated['started_to']);
        }

        if (array_key_exists('finished_from', $validated)) {
            $query->whereDate('finished_at', '>=', $validated['finished_from']);
        }

        if (array_key_exists('finished_to', $validated)) {
            $query->whereDate('finished_at', '<=', $validated['finished_to']);
        }

        $instances = $query->latest()->paginate(20);

        return response()->json($instances);
    }

    /**
     * Show a single instance with its node executions.
     */
    public function show(WorkflowInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        $instance->load([
            'nodeExecutions',
            'workflow.team:id,name',
            'workflow.createdBy:id,first_name,last_name,name,email',
            'dynamicFlows.childInstance.nodeExecutions',
        ]);

        $payload = $instance->toArray();
        $payload['workflow'] = $this->workflowManagementService->serializeWorkflow($instance->workflow, $this->actor());

        if ($instance->dynamicFlows->isNotEmpty()) {
            $payload['dynamic_flows'] = $instance->dynamicFlows->map(function ($df) {
                $child = $df->childInstance;

                return [
                    'id' => $df->id,
                    'node_key' => $df->node_key,
                    'status' => $df->status->value,
                    'child_instance_id' => $df->child_instance_id,
                    'child_instance' => $child ? [
                        'id' => $child->id,
                        'status' => $child->status->value,
                        'error' => $child->error,
                        'node_executions' => $child->nodeExecutions->map(fn ($ne) => [
                            'id' => $ne->id,
                            'node_key' => $ne->node_key,
                            'node_type' => $ne->node_type,
                            'status' => $ne->status->value,
                            'attempt' => $ne->attempt,
                            'input' => $ne->input,
                            'output' => $ne->output,
                            'error' => $ne->error,
                            'started_at' => $ne->started_at?->toISOString(),
                            'finished_at' => $ne->finished_at?->toISOString(),
                        ])->values(),
                    ] : null,
                ];
            })->values();
        }

        return response()->json($payload);
    }

    /**
     * Return all failed node executions for an instance, with their error details.
     */
    public function failures(WorkflowInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        $failedExecutions = WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->where('status', NodeExecutionStatus::Failed->value)
            ->orderBy('finished_at')
            ->get(['id', 'node_key', 'node_type', 'attempt', 'error', 'started_at', 'finished_at']);

        return response()->json([
            'instance_id' => $instance->id,
            'instance_status' => $instance->status,
            'instance_error' => $instance->error,
            'failed_nodes' => $failedExecutions,
        ]);
    }

    /**
     * Cancel a running instance.
     */
    public function cancel(WorkflowInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        if ($instance->isTerminal()) {
            return response()->json(['error' => 'Instance is already in a terminal state.'], 422);
        }

        $this->runtime->cancel($instance);

        return response()->json(['message' => 'Instance cancelled.'], 200);
    }

    /**
     * Retry execution from a specific node (operator recovery after permanent failure).
     */
    public function retryFromNode(Request $request, WorkflowInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        $nodeKey = (string) $request->input('node_key');
        if ($nodeKey === '') {
            return response()->json(['error' => 'node_key is required.'], 422);
        }

        $instance->load('workflowVersion');
        $this->runtime->retryFromNode($instance, $nodeKey);

        return response()->json(['message' => 'Retry dispatched.'], 202);
    }

    protected function authorizeInstance(WorkflowInstance $instance): void
    {
        if ((int) $instance->tenant_id !== (int) $this->actor()->tenant_id) {
            abort(403);
        }
    }

    protected function actor(): User
    {
        return auth('api')->user();
    }
}
