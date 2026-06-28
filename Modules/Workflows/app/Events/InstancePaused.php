<?php

namespace Modules\Workflows\Events;

use Modules\Workflows\Models\WorkflowInstance;

class InstancePaused
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly string $reason,
    ) {}
}
