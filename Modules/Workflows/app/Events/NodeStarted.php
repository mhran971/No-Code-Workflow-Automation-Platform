<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;

class NodeStarted implements ShouldBroadcast
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
        return 'node.started';
    }

    public function broadcastWith(): array
    {
        return [
            'instance_id' => $this->instance->id,
            'node_key'    => $this->execution->node_key,
            'node_type'   => $this->execution->node_type,
            'status'      => $this->execution->status->value,
            'attempt'     => $this->execution->attempt,
            'input'       => $this->execution->input ?? [],
            'output'      => null,
            'error'       => null,
            'started_at'  => $this->execution->started_at?->toISOString(),
            'finished_at' => null,
        ];
    }
}
