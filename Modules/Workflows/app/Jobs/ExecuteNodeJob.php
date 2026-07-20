<?php

namespace Modules\Workflows\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\WorkflowRuntime;

/**
 * Picks up a single node execution from the queue and delegates to WorkflowRuntime::advance().
 *
 * tries = 1 because our retry logic lives inside the runtime (it creates a new execution row
 * and re-dispatches with a delay). Queue-level retries would bypass idempotency keys and
 * re-run the executor against an already-succeeded execution.
 */
class ExecuteNodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $executionId,
        public readonly string $nodeCategory,
    ) {}

    public function handle(WorkflowRuntime $runtime): void
    {
        try {
            $runtime->advance($this->executionId);
        } catch (\Throwable $e) {
            $runtime->handleAdvanceFailure($this->executionId, $e);
            throw $e;
        }
    }

    public function timeout(): int
    {
        return match (NodeCategory::tryFrom($this->nodeCategory)) {
            NodeCategory::Ai => (int) config('workflows.execution.timeouts.ai', 120),
            NodeCategory::Action => (int) config('workflows.execution.timeouts.action', 60),
            default => (int) config('workflows.execution.timeouts.logic', 30),
        };
    }
}
