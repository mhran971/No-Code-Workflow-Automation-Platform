<?php

namespace Modules\Workflows\Console\Commands;

use Illuminate\Console\Command;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\Admission\InstanceAdmissionService;
use Modules\Workflows\Services\Execution\NodeExecutorRegistry;

/**
 * Runs every minute (registered in WorkflowsServiceProvider).
 *
 * Promotes `pending` instances to `running` when the tenant's concurrent-instance cap
 * allows it, then dispatches the trigger-node job that was withheld at admission time.
 * Processes oldest-first within each tenant to ensure FIFO ordering.
 */
class AdmitPendingInstancesCommand extends Command
{
    protected $signature = 'workflows:admit-pending';

    protected $description = 'Promote pending workflow instances to running as capacity frees up.';

    public function __construct(
        protected InstanceAdmissionService $admission,
        protected NodeExecutorRegistry $registry,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        WorkflowInstance::query()
            ->where('status', WorkflowInstanceStatus::Pending->value)
            ->orderBy('created_at')
            ->chunkById(100, function ($instances): void {
                foreach ($instances as $instance) {
                    if (! $this->admission->canAdmit((int) $instance->tenant_id)) {
                        continue;
                    }

                    $this->admitInstance($instance);
                }
            });

        return self::SUCCESS;
    }

    protected function admitInstance(WorkflowInstance $instance): void
    {
        $instance->update([
            'status' => WorkflowInstanceStatus::Running,
            'started_at' => now(),
        ]);

        $instance->workflow()->increment('active_instances');

        $firstExecution = WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->where('status', NodeExecutionStatus::Pending->value)
            ->orderBy('id')
            ->first();

        if ($firstExecution === null) {
            return;
        }

        $category = $this->registry->has($firstExecution->node_type)
            ? $this->registry->for($firstExecution->node_type)->category()
            : NodeCategory::Logic;

        $queue = $category === NodeCategory::Action
            || $category === NodeCategory::Ai
            ? (string) config('workflows.execution.queues.actions', 'workflow-actions')
            : (string) config('workflows.execution.queues.control', 'workflow-control');

        ExecuteNodeJob::dispatch($firstExecution->id, $category->value)->onQueue($queue);
    }
}
