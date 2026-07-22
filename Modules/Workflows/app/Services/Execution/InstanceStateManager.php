<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Workflows\Enums\DynamicFlowStatus;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Events\ChildInstanceEventForwarded;
use Modules\Workflows\Events\InstanceCancelled;
use Modules\Workflows\Events\InstanceCompleted;
use Modules\Workflows\Events\InstanceFailed;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;

class InstanceStateManager
{
    protected array $allowed = [
        'pending'   => ['running', 'failed', 'cancelled'],
        'running'   => ['completed', 'failed', 'waiting', 'cancelled'],
        'waiting'   => ['running', 'failed', 'cancelled'],
        'paused'    => ['running'],
        'failed'    => ['running'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        protected NodeExecutorRegistry $registry,
        protected string $controlQueue,
        protected string $actionQueue,
    ) {}

    public function transitionTo(
        WorkflowInstance $instance,
        WorkflowInstanceStatus $to,
        ?array $error = null,
    ): void {
        $this->assertTransition($instance, $to);

        match ($to) {
            WorkflowInstanceStatus::Completed => $this->complete($instance),
            WorkflowInstanceStatus::Failed => $this->fail($instance, $error ?? []),
            WorkflowInstanceStatus::Cancelled => $this->cancel($instance),
            WorkflowInstanceStatus::Waiting => $this->markWaiting($instance),
            WorkflowInstanceStatus::Running => $this->resume($instance),
        };
    }

    protected function complete(WorkflowInstance $instance): void
    {
        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Completed,
            'finished_at' => $instance->finished_at ?? now(),
        ])->save();

        $instance->workflow()->decrement('active_instances');

        $this->broadcast(new InstanceCompleted($instance));
        $this->wakeParentExecution($instance);
    }

    protected function fail(WorkflowInstance $instance, array $error): void
    {
        WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->where('status', NodeExecutionStatus::Waiting->value)
            ->update(['status' => NodeExecutionStatus::Consumed->value, 'finished_at' => now()]);

        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Failed,
            'error' => $error,
            'finished_at' => now(),
        ])->save();

        $this->broadcast(new InstanceFailed($instance));
        $this->wakeParentExecution($instance);
    }

    protected function cancel(WorkflowInstance $instance): void
    {
        WorkflowNodeExecution::query()
            ->where('instance_id', $instance->id)
            ->whereIn('status', [
                NodeExecutionStatus::Pending->value,
                NodeExecutionStatus::Running->value,
                NodeExecutionStatus::Waiting->value,
            ])
            ->update(['status' => NodeExecutionStatus::Consumed->value, 'finished_at' => now()]);

        WorkflowTask::query()
            ->where('instance_id', $instance->id)
            ->where('status', 'open')
            ->update(['status' => 'cancelled']);

        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Cancelled,
            'finished_at' => now(),
        ])->save();

        $this->broadcast(new InstanceCancelled($instance));
    }

    protected function markWaiting(WorkflowInstance $instance): void
    {
        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Waiting,
        ])->save();
    }

    protected function resume(WorkflowInstance $instance): void
    {
        $instance->forceFill([
            'status' => WorkflowInstanceStatus::Running,
        ])->save();
    }

    protected function assertTransition(WorkflowInstance $instance, WorkflowInstanceStatus $to): void
    {
        $from = $instance->status?->value ?? 'pending';
        $toValue = $to->value;
        $allowedFrom = $this->allowed[$from] ?? [];

        if (! in_array($toValue, $allowedFrom, true)) {
            throw new \LogicException(
                "Cannot transition workflow instance [{$instance->id}] from '{$from}' to '{$toValue}'."
            );
        }
    }

    protected function broadcast(object $event): void
    {
        DB::afterCommit(function () use ($event) {
            Event::dispatch($event);

            $instance = $event->instance ?? null;
            if ($instance instanceof WorkflowInstance && $instance->parent_instance_id) {
                $parentInstance = WorkflowInstance::find($instance->parent_instance_id);
                if ($parentInstance) {
                    Log::info('workflow.child_event.forwarding', [
                        'child_instance_id' => $instance->id,
                        'parent_instance_id' => $parentInstance->id,
                        'event' => $event->broadcastAs(),
                    ]);
                    Event::dispatch(new ChildInstanceEventForwarded(
                        $instance,
                        $parentInstance,
                        $event->broadcastAs(),
                        $event->broadcastWith(),
                    ));
                }
            }
        });
    }

    protected function wakeParentExecution(WorkflowInstance $instance): void
    {
        $parentExecutionId = $instance->parent_execution_id;

        if ($parentExecutionId === null) {
            $dynamicFlow = WorkflowDynamicFlow::query()
                ->where('child_instance_id', $instance->id)
                ->where('status', DynamicFlowStatus::Executing)
                ->first();
            if ($dynamicFlow !== null) {
                $parentExecutionId = $dynamicFlow->execution_id;
            }
        }

        if ($parentExecutionId === null) {
            return;
        }

        $parentExecution = WorkflowNodeExecution::query()->find($parentExecutionId);
        if ($parentExecution === null || $parentExecution->status !== NodeExecutionStatus::Waiting) {
            return;
        }

        $parentExecution->update([
            'status' => NodeExecutionStatus::Pending,
            'wait_until' => null,
        ]);

        $category = $this->resolveCategory($parentExecution->node_type);
        $queue = $category === NodeCategory::Action || $category === NodeCategory::Ai
            ? $this->actionQueue
            : $this->controlQueue;

        ExecuteNodeJob::dispatch($parentExecution->id, $category->value)
            ->onQueue($queue);
    }

    protected function resolveCategory(string $nodeType): NodeCategory
    {
        if ($this->registry->has($nodeType)) {
            return $this->registry->for($nodeType)->category();
        }

        return NodeCategory::Logic;
    }
}
