<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;

class NodeWaiting implements ShouldBroadcastNow
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly WorkflowNodeExecution $execution,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workflow-instance.'.$this->instance->id)];
    }

    public function broadcastAs(): string
    {
        return 'node.waiting';
    }

    public function broadcastWith(): array
    {
        return [
            'instance_id' => $this->instance->id,
            'node_key'    => $this->execution->node_key,
            'node_type'   => $this->execution->node_type,
            'status'      => 'waiting',
            'attempt'     => $this->execution->attempt,
            'input'       => $this->execution->input ?? [],
            'output'      => null,
            'error'       => null,
            'wait_type'   => $this->execution->wait_type?->value,
            'wait_until'  => $this->execution->wait_until?->toISOString(),
            'started_at'  => $this->execution->started_at?->toISOString(),
            'finished_at' => null,
        ];
    }
}
