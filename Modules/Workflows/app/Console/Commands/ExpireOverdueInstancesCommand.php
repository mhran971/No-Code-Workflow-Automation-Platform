<?php

namespace Modules\Workflows\Console\Commands;

use Illuminate\Console\Command;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\InstanceFailed;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;

/**
 * Runs daily (registered in WorkflowsServiceProvider).
 *
 * Marks workflow instances that have exceeded `workflows.execution.max_instance_duration`
 * as `failed` and consumes any parked waits, so timer-scan jobs stop firing for dead instances.
 * This is the global deadline backstop for orphaned runs (e.g., task nobody ever completes).
 */
class ExpireOverdueInstancesCommand extends Command
{
    protected $signature = 'workflows:expire-overdue';

    protected $description = 'Fail workflow instances that have exceeded the global duration limit.';

    public function handle(): int
    {
        $maxMinutes = (int) config('workflows.execution.max_instance_duration', 30 * 24 * 60);
        $cutoff = now()->subMinutes($maxMinutes);

        WorkflowInstance::query()
            ->whereNotIn('status', array_map(
                fn (WorkflowInstanceStatus $s) => $s->value,
                array_filter(WorkflowInstanceStatus::cases(), fn ($s) => $s->isTerminal()),
            ))
            ->where('started_at', '<=', $cutoff)
            ->chunkById(100, function ($instances): void {
                foreach ($instances as $instance) {
                    $this->expireInstance($instance);
                }
            });

        return self::SUCCESS;
    }

    protected function expireInstance(WorkflowInstance $instance): void
    {
        WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->whereIn('status', [
                NodeExecutionStatus::Pending->value,
                NodeExecutionStatus::Running->value,
                NodeExecutionStatus::Waiting->value,
            ])
            ->update(['status' => NodeExecutionStatus::Consumed->value, 'finished_at' => now()]);

        $instance->update([
            'status' => WorkflowInstanceStatus::Failed,
            'error' => ['message' => 'Instance exceeded the global duration limit.'],
            'finished_at' => now(),
        ]);

        event(new InstanceFailed($instance));
    }
}
