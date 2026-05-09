<?php

namespace Modules\Workflows\Enums;

enum WorkflowInstanceStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
