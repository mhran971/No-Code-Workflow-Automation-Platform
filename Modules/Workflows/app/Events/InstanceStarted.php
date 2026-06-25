<?php

namespace Modules\Workflows\Events;

use Modules\Workflows\Models\WorkflowInstance;

class InstanceStarted
{
    public function __construct(public readonly WorkflowInstance $instance) {}
}
