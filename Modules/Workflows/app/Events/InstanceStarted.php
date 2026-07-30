<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Workflows\Models\WorkflowInstance;

class InstanceStarted implements ShouldBroadcastNow
{
    public function __construct(public readonly WorkflowInstance $instance) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workflow-instance.'.$this->instance->id)];
    }

    public function broadcastAs(): string
    {
        return 'instance.started';
    }

    public function broadcastWith(): array
    {
        return [
            'instance_id' => $this->instance->id,
            'status' => 'running',
            'started_at' => $this->instance->created_at?->toISOString(),
        ];
    }
}
