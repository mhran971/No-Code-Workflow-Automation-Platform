<?php

namespace Modules\Workflows\Enums;

enum NodeCategory : string {
    case Trigger = 'trigger';
    case Logic = 'logic';
    case Ai = 'ai';
    case Flows = 'flows';
    case Action = 'action';
}
