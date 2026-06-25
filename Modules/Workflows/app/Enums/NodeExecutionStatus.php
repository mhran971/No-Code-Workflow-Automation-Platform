<?php

namespace Modules\Workflows\Enums;

enum NodeExecutionStatus: string
{
    case Pending = 'pending';     // created, not yet run (an active token)
    case Running = 'running';     // executing now (or side effect in flight)
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Waiting = 'waiting';     // parked on a durable wait (task / merge)
    case Skipped = 'skipped';     // branch not taken
    case Consumed = 'consumed';   // join loser / terminated token

    /**
     * Statuses that represent an active path still owed work by the engine.
     */
    public function isLive(): bool
    {
        return in_array($this, [self::Pending, self::Running, self::Waiting], true);
    }

    /**
     * Whether the engine may claim and advance this execution.
     */
    public function isRunnable(): bool
    {
        return in_array($this, [self::Pending, self::Waiting], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Skipped, self::Consumed], true);
    }
}
