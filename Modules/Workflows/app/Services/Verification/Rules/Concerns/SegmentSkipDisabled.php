<?php

namespace Modules\Workflows\Services\Verification\Rules\Concerns;

trait SegmentSkipDisabled
{
    public function skipForSegment(): bool
    {
        return false;
    }
}
