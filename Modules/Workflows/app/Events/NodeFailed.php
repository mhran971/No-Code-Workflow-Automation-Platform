<?php

namespace Modules\Workflows\Events;

use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;

class NodeFailed
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly WorkflowNodeExecution $execution,
        public readonly bool $willRetry,
    ) {}
}
