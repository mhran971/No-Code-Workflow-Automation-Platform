<?php

namespace Modules\Workflows\Enums;

enum TriggerType: string
{
    case Manual = 'manual';
    case Form = 'form';
    case Webhook = 'webhook';

    // Phase 3 (out of scope): Schedule, Event, SubWorkflow.
}
