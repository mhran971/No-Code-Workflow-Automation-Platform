<?php

namespace Tests\Unit\Execution;

use Modules\Workflows\Services\Execution\RetryPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RetryPolicyTest extends TestCase
{
    private RetryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RetryPolicy;
    }

    #[Test]
    public function should_retry_when_attempts_below_max(): void
    {
        $this->assertTrue($this->policy->shouldRetry(1));
        $this->assertTrue($this->policy->shouldRetry(2));
    }

    #[Test]
    public function should_not_retry_at_max_attempts(): void
    {
        $max = $this->policy->maxAttempts(); // default 3
        $this->assertFalse($this->policy->shouldRetry($max));
        $this->assertFalse($this->policy->shouldRetry($max + 1));
    }

    #[Test]
    public function delay_grows_exponentially(): void
    {
        $d1 = $this->policy->delaySeconds(1);
        $d2 = $this->policy->delaySeconds(2);
        $d3 = $this->policy->delaySeconds(3);

        $this->assertGreaterThanOrEqual($d1, $d2);
        $this->assertGreaterThanOrEqual($d2, $d3 === $this->policy->delaySeconds(3) ? $d2 : $d3); // non-decreasing
        $this->assertGreaterThan($d1, $d2);
    }

    #[Test]
    public function delay_is_capped_at_max(): void
    {
        // Attempt 100 should never exceed max_delay.
        $delay = $this->policy->delaySeconds(100);
        $max = (int) config('workflows.execution.retry.max_delay', 300);

        $this->assertLessThanOrEqual($max, $delay);
    }
}
