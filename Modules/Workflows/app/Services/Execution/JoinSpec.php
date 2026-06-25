<?php

namespace Modules\Workflows\Services\Execution;

/**
 * The synchronization contract for a `merge` node, precomputed from the plan so runtime never counts
 * branches at execution time (which would race with not-yet-started branches).
 */
final class JoinSpec
{
    public const MODE_PARALLEL = 'parallel';

    public const MODE_CONDITIONAL = 'conditional';

    public function __construct(
        public readonly string $mode,        // parallel (wait-all) | conditional (first-arrival)
        public readonly int $expectedCount,  // number of branches that will arrive
        public readonly ?int $timeoutSeconds = null,
    ) {}

    public function isParallel(): bool
    {
        return $this->mode === self::MODE_PARALLEL;
    }
}
