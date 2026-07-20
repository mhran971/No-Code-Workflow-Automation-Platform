<?php

namespace Tests\Unit\Execution;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Executors\SubWorkflowExecutor;
use Modules\Workflows\Services\Execution\WorkflowDispatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubWorkflowExecutorTest extends TestCase
{
    private SubWorkflowExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new SubWorkflowExecutor(app(WorkflowDispatcher::class));
    }

    #[Test]
    public function type_is_sub_workflow(): void
    {
        $this->assertSame('sub-workflow', $this->executor->type());
    }

    #[Test]
    public function category_is_flows(): void
    {
        $this->assertSame(NodeCategory::Flows, $this->executor->category());
    }
}
