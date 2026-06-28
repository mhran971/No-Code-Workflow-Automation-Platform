<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;

/**
 * Assignee inbox and task submission for `task-node` executions.
 */
class WorkflowTaskController extends Controller
{
    /**
     * List open tasks assigned to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $tasks = WorkflowTask::query()
            ->where('assignee_id', $this->actor()->id)
            ->where('tenant_id', $this->actor()->tenant_id)
            ->where('status', 'open')
            ->latest('due_at')
            ->paginate(20);

        return response()->json($tasks);
    }

    /**
     * Submit a response for a task, resuming the parked execution.
     */
    public function submit(Request $request, WorkflowTask $task): JsonResponse
    {
        if ((int) $task->tenant_id !== (int) $this->actor()->tenant_id) {
            abort(403);
        }

        if ($task->status !== 'open') {
            return response()->json(['error' => 'Task is already closed.'], 422);
        }

        $task->update([
            'status' => 'completed',
            'response' => $request->input('response', []),
            'completed_by_id' => $this->actor()->id,
            'completed_at' => now(),
        ]);

        $this->resumeExecution((int) $task->execution_id);

        return response()->json(['message' => 'Task submitted.'], 200);
    }

    protected function resumeExecution(int $executionId): void
    {
        $execution = WorkflowNodeExecution::query()->find($executionId);
        if ($execution === null || $execution->status !== NodeExecutionStatus::Waiting) {
            return;
        }

        $execution->update(['status' => NodeExecutionStatus::Pending, 'wait_until' => null]);

        ExecuteNodeJob::dispatch($execution->id, NodeCategory::Action->value)
            ->onQueue((string) config('workflows.execution.queues.actions', 'workflow-actions'));
    }

    protected function actor(): User
    {
        return auth('api')->user();
    }
}
