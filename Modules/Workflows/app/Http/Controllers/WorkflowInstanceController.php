<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\WorkflowRuntime;

/**
 * Operator view of running instances: list, inspect, cancel, and retry-from-node.
 */
class WorkflowInstanceController extends Controller
{
    public function __construct(protected WorkflowRuntime $runtime) {}

    /**
     * List instances for a given workflow (most recent first).
     */
    public function index(Request $request, Workflow $workflow): JsonResponse
    {
        $instances = WorkflowInstance::query()
            ->where('workflow_id', $workflow->id)
            ->where('tenant_id', $this->actor()->tenant_id)
            ->latest()
            ->paginate(20);

        return response()->json($instances);
    }

    /**
     * Show a single instance with its node executions.
     */
    public function show(WorkflowInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        $instance->load('nodeExecutions');

        return response()->json($instance);
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
