<?php

namespace Modules\Workflows\Enums;

enum ParallelStrategy: string
{
    case ForkJoin      = 'fork_join';
    case FireAndForget = 'fire_and_forget';
}
