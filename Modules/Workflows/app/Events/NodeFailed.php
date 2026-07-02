<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;

class NodeFailed implements ShouldBroadcastNow
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly WorkflowNodeExecution $execution,
        public readonly bool $willRetry,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workflow-instance.'.$this->instance->id)];
    }

    public function broadcastAs(): string
    {
        return 'node.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'instance_id' => $this->instance->id,
            'node_key'    => $this->execution->node_key,
            'node_type'   => $this->execution->node_type,
            'status'      => 'failed',
            'attempt'     => $this->execution->attempt,
            'input'       => $this->execution->input ?? [],
            'output'      => null,
            'error'       => $this->execution->error,
            'will_retry'  => $this->willRetry,
            'started_at'  => $this->execution->started_at?->toISOString(),
            'finished_at' => $this->execution->finished_at?->toISOString(),
        ];
    }
}
