<?php

namespace Modules\Workflows\Services\Execution\Data;

use DateTimeInterface;
use Modules\Workflows\app\Enums\ResultKind;
use Modules\Workflows\Enums\WaitType;
use Modules\Workflows\Services\Execution\Data\PlanEdge;
use Throwable;

/**
 * The outcome of running one node. The runtime's applyResult() interprets the kind to advance, park, retry,
 * or finish the token. Construct via the named factory methods.
 */
final class NodeExecutionResult
{
    /**
     * @param  list<PlanEdge>  $edges
     * @param  array<string, mixed>  $output
     */
    private function __construct(
        public readonly ResultKind $kind,
        public readonly array $edges = [],
        public readonly array $output = [],
        public readonly ?Throwable $error = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $retryable = false,
        public readonly ?DateTimeInterface $waitUntil = null,
        public readonly ?WaitType $waitType = null,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @param  list<PlanEdge>  $edges
     * @param  array<string, mixed>  $output
     */
    public static function proceed(array $edges = [], array $output = []): self
    {
        return new self(ResultKind::Proceed, edges: $edges, output: $output);
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public static function branch(PlanEdge $edge, array $output = []): self
    {
        return new self(ResultKind::Branch, edges: [$edge], output: $output);
    }

    public static function wait(?DateTimeInterface $until = null, ?WaitType $type = null, ?string $reason = null): self
    {
        return new self(ResultKind::Wait, waitUntil: $until, waitType: $type, reason: $reason);
    }

    public static function fail(Throwable|string $error, bool $retryable = false): self
    {
        return new self(
            ResultKind::Fail,
            error: $error instanceof Throwable ? $error : null,
            errorMessage: $error instanceof Throwable ? $error->getMessage() : $error,
            retryable: $retryable,
        );
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public static function terminate(array $output = []): self
    {
        return new self(ResultKind::Terminate, output: $output);
    }

    public static function noop(): self
    {
        return new self(ResultKind::Noop);
    }
}
