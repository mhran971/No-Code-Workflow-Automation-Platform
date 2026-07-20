<?php

namespace Modules\Workflows\Enums;

enum DynamicFlowStatus: string
{
    case AwaitingDesign = 'awaiting_design';
    case Executing = 'executing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
