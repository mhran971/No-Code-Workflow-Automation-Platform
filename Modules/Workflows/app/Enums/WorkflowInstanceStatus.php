<?php

namespace Modules\Workflows\Enums;

enum WorkflowInstanceStatus: string
{
    case Pending = 'pending';     // admitted-but-not-started (admission control)
    case Running = 'running';
    case Waiting = 'waiting';     // all live tokens parked on durable waits
    case Paused = 'paused';       // needs_review / manual hold
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }
}
