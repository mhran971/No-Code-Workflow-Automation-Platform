<?php

namespace Modules\Workflows\Services\Execution\Exceptions;

use RuntimeException;

/**
 * Raised by anything that talks to the external RAG AI service (generate/classify). Carries enough
 * information for a NodeExecutor to decide retryability without re-deriving it from an HTTP status.
 */
class AiServiceException extends RuntimeException
{
    public function __construct(string $message, protected int $status, protected bool $retryable)
    {
        parent::__construct($message);
    }

    /**
     * The HTTP status of the underlying response, or 200 for a "soft failure"
     * (200 OK with `success: false` in the body).
     */
    public function status(): int
    {
        return $this->status;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
