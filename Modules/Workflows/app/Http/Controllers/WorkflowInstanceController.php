<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\WorkflowRuntime;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Stream live execution events for a single instance via Server-Sent Events.
     * Polls workflow_node_executions every 1 s and pushes deltas until the instance
     * reaches a terminal state or the client disconnects.
     */
    public function stream(WorkflowInstance $instance): StreamedResponse
    {
        $this->authorizeInstance($instance);

        return response()->stream(function () use ($instance): void {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $lastStatuses = [];  // "node_key:attempt" => status string
            $lastCtxHash  = null;
            $deadline     = time() + 30 * 60; // 30-minute safety cap

            while (! connection_aborted() && time() < $deadline) {
                $instance->refresh();

                // Node execution deltas — emit only changed rows.
                $executions = WorkflowNodeExecution::query()
                    ->where('instance_id', $instance->id)
                    ->get(['node_key', 'node_type', 'status', 'attempt',
                        'input', 'output', 'error', 'started_at', 'finished_at']);

                foreach ($executions as $exec) {
                    $trackKey = $exec->node_key.':'.$exec->attempt;
                    if (($lastStatuses[$trackKey] ?? null) !== $exec->status->value) {
                        echo 'data: '.json_encode([
                            'type'        => 'node',
                            'node_key'    => $exec->node_key,
                            'node_type'   => $exec->node_type,
                            'status'      => $exec->status->value,
                            'attempt'     => $exec->attempt,
                            'input'       => $exec->input ?? [],
                            'output'      => $exec->status->isTerminal() ? ($exec->output ?? []) : null,
                            'error'       => $exec->error,
                            'started_at'  => $exec->started_at?->toISOString(),
                            'finished_at' => $exec->finished_at?->toISOString(),
                        ])."\n\n";
                        $lastStatuses[$trackKey] = $exec->status->value;
                    }
                }

                // Instance heartbeat (status only, every tick).
                echo 'data: '.json_encode([
                    'type'   => 'instance',
                    'status' => $instance->status->value,
                ])."\n\n";

                // Runtime context — emit only when changed.
                $ctxHash = md5((string) json_encode($instance->context));
                if ($ctxHash !== $lastCtxHash) {
                    echo 'data: '.json_encode([
                        'type'    => 'context',
                        'context' => $instance->context ?? [],
                    ])."\n\n";
                    $lastCtxHash = $ctxHash;
                }

                ob_flush();
                flush();

                if ($instance->status->isTerminal()) {
                    echo 'data: '.json_encode([
                        'type'   => 'done',
                        'status' => $instance->status->value,
                    ])."\n\n";
                    ob_flush();
                    flush();
                    break;
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
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
