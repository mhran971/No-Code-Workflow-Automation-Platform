<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Validation\ValidationException;
use Modules\Workflows\Services\Execution\Exceptions\UnsupportedNodeTypeException;
use Throwable;

/**
 * Classifies a thrown exception as transiently retryable or permanently non-retryable.
 *
 * Defaults to retryable — a transient assumption is safer than permanently failing a step due to a
 * misclassified error. Add concrete non-retryable classes as they become known.
 */
class FailureClassifier
{
    /** @var list<class-string<Throwable>> */
    private array $nonRetryableClasses = [
        ValidationException::class,
        UnsupportedNodeTypeException::class,
        \InvalidArgumentException::class,
        \LogicException::class,
    ];

    public function isRetryable(Throwable $e): bool
    {
        foreach ($this->nonRetryableClasses as $class) {
            if ($e instanceof $class) {
                return false;
            }
        }

        return true;
    }
}
