<?php

namespace Modules\Workflows\Enums;

enum WorkflowStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Deleted = 'deleted';
}
