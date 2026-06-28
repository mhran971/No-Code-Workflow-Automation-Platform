<?php

namespace Modules\Workflows\Services\Execution\Exceptions;

use RuntimeException;

class UnsupportedNodeTypeException extends RuntimeException
{
    public static function for(string $type): self
    {
        return new self("No executor registered for node type [{$type}].");
    }
}
