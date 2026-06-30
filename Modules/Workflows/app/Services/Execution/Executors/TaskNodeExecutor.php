<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\WaitType;
use Modules\Workflows\Models\WorkflowTask;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

/**
 * Creates a human-task row and parks the execution until the assignee submits a response
 * (via WorkflowTaskController) or the SLA timer fires.
 *
 * On resume (user submits or SLA expires), the executor is invoked again. It finds the
 * task row and proceeds if completed/expired, or re-parks if still open and within policy.
 */
class TaskNodeExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'task-node';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Action;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $executionId = $context->execution()->id;
        $config = $context->config();

        $task = WorkflowTask::query()->where('execution_id', $executionId)->first();

        // Resume path: task was completed or expired since we last parked.
        if ($task !== null && $task->status !== 'open') {
            $response = $task->response ?? [];
            $context->mergeContext(['task_response' => $response]);

            return NodeExecutionResult::proceed(
                $context->plan()->outgoing($context->nodeKey()),
                ['task_response' => $response, 'task_status' => $task->status],
            );
        }

        // SLA-breach path: timer fired but task still open — expire and proceed.
        if ($task !== null && $context->execution()->wait_until !== null && now()->gte($context->execution()->wait_until)) {
            $task->update(['status' => 'expired']);

            return NodeExecutionResult::proceed(
                $context->plan()->outgoing($context->nodeKey()),
                ['task_response' => null, 'task_status' => 'expired'],
            );
        }

        // Initial creation path.
        $dueHours = (int) ($config['dueWithin'] ?? config('workflows.execution.task.default_due_within_hours', 24));
        $dueAt = now()->addHours($dueHours);

        if ($task === null) {
            WorkflowTask::query()->create([
                'instance_id' => $context->instanceId(),
                'execution_id' => $executionId,
                'tenant_id' => $context->instance()->tenant_id,
                'node_key' => $context->nodeKey(),
                'assignee_id' => $this->resolveAssigneeId($context, $config),
                'title' => $context->render((string) ($config['title'] ?? 'Task')),
                'description' => $context->render((string) ($config['description'] ?? '')),
                'input_schema' => $config['inputFields'] ?? null,
                'status' => 'open',
                'due_at' => $dueAt,
            ]);
        }

        return NodeExecutionResult::wait($dueAt, WaitType::TaskSla, 'Waiting for task completion');
    }

    protected function resolveAssigneeId(NodeExecutionContext $context, array $config): ?int
    {
        $expr = $config['assignTo'] ?? null;
        if ($expr === null || $expr === '') {
            return null;
        }
        // assignTo may be a plain numeric string (user ID from the picker) or
        // a template expression like {{context.user_id}} — evaluate handles both.
        $value = $context->render((string) $expr);

        return is_numeric($value) ? (int) $value : null;
    }
}
