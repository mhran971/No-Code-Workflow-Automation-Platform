<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Workflows\Models\WorkflowInstance;

/**
 * Re-broadcasts any child instance event on the parent instance's channel,
 * so the parent's frontend can display nested execution progress.
 */
class ChildInstanceEventForwarded implements ShouldBroadcastNow
{
    public function __construct(
        public readonly WorkflowInstance $childInstance,
        public readonly WorkflowInstance $parentInstance,
        public readonly string $originalEventName,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workflow-instance.'.$this->parentInstance->id)];
    }

    public function broadcastAs(): string
    {
        return 'child.'.$this->originalEventName;
    }

    public function broadcastWith(): array
    {
        return array_merge($this->payload, [
            'child_instance_id' => $this->childInstance->id,
        ]);
    }
}
