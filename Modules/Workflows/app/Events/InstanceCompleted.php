<?php

namespace Modules\Workflows\Events;

use Modules\Workflows\Models\WorkflowInstance;

class InstanceCompleted
{
    public function __construct(public readonly WorkflowInstance $instance) {}
}
