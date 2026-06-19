<?php

namespace Modules\Workflows\Enums;

enum EdgeBranchType: string
{
    case Default     = 'default';
    case Conditional = 'conditional';
    case Parallel    = 'parallel';
}
