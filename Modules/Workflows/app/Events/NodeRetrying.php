<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;

class NodeRetrying implements ShouldBroadcast
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly WorkflowNodeExecution $failedExecution,
        public readonly WorkflowNodeExecution $retryExecution,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workflow-instance.'.$this->instance->id)];
    }

    public function broadcastAs(): string
    {
        return 'node.retrying';
    }

    public function broadcastWith(): array
    {
        return [
            'instance_id'    => $this->instance->id,
            'node_key'       => $this->retryExecution->node_key,
            'node_type'      => $this->retryExecution->node_type,
            'status'         => 'pending',
            'attempt'        => $this->retryExecution->attempt,
            'failed_attempt' => $this->failedExecution->attempt,
        ];
    }
}
