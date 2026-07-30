<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Workflows\Models\WorkflowInstance;

class InstanceCompleted implements ShouldBroadcastNow
{
    public function __construct(public readonly WorkflowInstance $instance) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workflow-instance.'.$this->instance->id)];
    }

    public function broadcastAs(): string
    {
        return 'instance.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'instance_id' => $this->instance->id,
            'status' => 'completed',
            'context' => $this->instance->context ?? [],
            'finished_at' => $this->instance->finished_at?->toISOString(),
        ];
    }
}
