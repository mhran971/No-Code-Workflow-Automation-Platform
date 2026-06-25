<?php

namespace Tests\Unit\Execution;

use Illuminate\Validation\ValidationException;
use Modules\Workflows\Services\Execution\Exceptions\UnsupportedNodeTypeException;
use Modules\Workflows\Services\Execution\FailureClassifier;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class FailureClassifierTest extends TestCase
{
    private FailureClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new FailureClassifier;
    }

    #[Test]
    public function runtime_exceptions_are_retryable(): void
    {
        $this->assertTrue($this->classifier->isRetryable(new RuntimeException('network blip')));
    }

    #[Test]
    public function validation_exceptions_are_not_retryable(): void
    {
        $e = ValidationException::withMessages(['field' => 'bad']);
        $this->assertFalse($this->classifier->isRetryable($e));
    }

    #[Test]
    public function unsupported_node_type_is_not_retryable(): void
    {
        $e = UnsupportedNodeTypeException::for('unknown-node');
        $this->assertFalse($this->classifier->isRetryable($e));
    }

    #[Test]
    public function invalid_argument_is_not_retryable(): void
    {
        $this->assertFalse($this->classifier->isRetryable(new \InvalidArgumentException));
    }
}
