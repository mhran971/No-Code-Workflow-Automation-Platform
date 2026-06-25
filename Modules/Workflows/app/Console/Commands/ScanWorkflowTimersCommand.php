<?php

namespace Modules\Workflows\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\NodeExecutorRegistry;

/**
 * Runs every minute (registered in WorkflowsServiceProvider).
 *
 * Promotes any `waiting` execution whose `wait_until` has passed back to `pending`
 * and re-dispatches its job. Handles both `merge_timeout` (parallel merge whose
 * deadline expired before all branches arrived) and `task_sla` (overdue human task).
 */
class ScanWorkflowTimersCommand extends Command
{
    protected $signature = 'workflows:scan-timers';

    protected $description = 'Promote overdue waiting executions back to pending and re-dispatch their jobs.';

    public function __construct(protected NodeExecutorRegistry $registry)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        WorkflowNodeExecution::query()
            ->where('status', NodeExecutionStatus::Waiting->value)
            ->where('wait_until', '<=', now())
            ->orderBy('wait_until')
            ->chunkById(100, function ($executions): void {
                foreach ($executions as $execution) {
                    $this->promoteExecution($execution);
                }
            });

        return self::SUCCESS;
    }

    protected function promoteExecution(WorkflowNodeExecution $execution): void
    {
        DB::transaction(function () use ($execution): void {
            $locked = WorkflowNodeExecution::query()
                ->lockForUpdate()
                ->find($execution->id);

            if ($locked === null || $locked->status !== NodeExecutionStatus::Waiting) {
                return;
            }

            $locked->update([
                'status' => NodeExecutionStatus::Pending,
                'wait_until' => null,
            ]);

            $category = $this->registry->has($locked->node_type)
                ? $this->registry->for($locked->node_type)->category()
                : \Modules\Workflows\Enums\NodeCategory::Logic;

            $queue = $category === \Modules\Workflows\Enums\NodeCategory::Action
                || $category === \Modules\Workflows\Enums\NodeCategory::Ai
                ? (string) config('workflows.execution.queues.actions', 'workflow-actions')
                : (string) config('workflows.execution.queues.control', 'workflow-control');

            ExecuteNodeJob::dispatch($locked->id, $category->value)->onQueue($queue);
        });
    }
}
