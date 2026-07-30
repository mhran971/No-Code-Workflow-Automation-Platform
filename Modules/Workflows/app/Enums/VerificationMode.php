<?php

namespace Modules\Workflows\Enums;

enum VerificationMode: string
{
    case Full = 'full';
    case Segment = 'segment';
}
