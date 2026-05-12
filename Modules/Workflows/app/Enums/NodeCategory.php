<?php

namespace Modules\Workflows\Enums;

enum NodeCategory : string {
    case Trigger = 'trigger';
    case Action = 'action';
    case Control = 'control';
    case Data = 'data';
    case Integration = 'integration';
    case Ai = 'ai';
    case Human = 'human';
    case Dynamic = 'dynamic';
}
