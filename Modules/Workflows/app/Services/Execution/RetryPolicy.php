<?php

namespace Modules\Workflows\Services\Execution;

class RetryPolicy
{
    public function shouldRetry(int $currentAttempt): bool
    {
        return $currentAttempt < $this->maxAttempts();
    }

    public function maxAttempts(): int
    {
        return (int) config('workflows.execution.retry.max_attempts', 3);
    }

    public function delaySeconds(int $attempt): int
    {
        $base = (int) config('workflows.execution.retry.base_delay', 5);
        $max = (int) config('workflows.execution.retry.max_delay', 300);
        $strategy = (string) config('workflows.execution.retry.strategy', 'exponential');

        $delay = ($strategy === 'exponential')
            ? (int) ($base * (2 ** ($attempt - 1)))
            : $base;

        return min($delay, $max);
    }
}
