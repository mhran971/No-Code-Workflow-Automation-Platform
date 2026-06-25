<?php

namespace Modules\Workflows\Events;

use Modules\Workflows\Models\WorkflowInstance;

class InstanceFailed
{
    public function __construct(public readonly WorkflowInstance $instance) {}
}
