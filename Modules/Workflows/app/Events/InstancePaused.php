<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Workflows\Models\WorkflowInstance;

class InstancePaused implements ShouldBroadcastNow
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workflow-instance.'.$this->instance->id)];
    }

    public function broadcastAs(): string
    {
        return 'instance.paused';
    }

    public function broadcastWith(): array
    {
        return [
            'instance_id' => $this->instance->id,
            'status' => 'paused',
            'reason' => $this->reason,
        ];
    }
}
