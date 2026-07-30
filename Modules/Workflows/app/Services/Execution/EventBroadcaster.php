<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Workflows\Events\ChildInstanceEventForwarded;
use Modules\Workflows\Models\WorkflowInstance;

class EventBroadcaster
{
    public function broadcast(object $event): void
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
}
