<?php

namespace Modules\Workflows\Notifications;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowInstance;

class DynamicFlowDesignRequested extends Notification implements ShouldBroadcastNow
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly WorkflowDynamicFlow $dynamicFlow,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'instance_id' => $this->instance->id,
            'workflow_id' => $this->instance->workflow_id,
            'workflow_name' => $this->instance->workflow->name ?? 'Unknown',
            'dynamic_flow_id' => $this->dynamicFlow->id,
            'node_key' => $this->dynamicFlow->node_key,
            'message' => 'A dynamic sub-flow design is required for this workflow instance.',
        ];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage($this->toArray($notifiable));
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->instance->workflow->created_by_id)];
    }

    public function broadcastAs(): string
    {
        return 'dynamic-flow.design-requested';
    }

    public function broadcastWith(): array
    {
        return $this->toArray($notifiable ?? auth()->user());
    }
}
