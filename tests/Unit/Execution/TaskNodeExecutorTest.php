<?php

namespace Tests\Unit\Execution;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Executors\TaskNodeExecutor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pure / meta tests for TaskNodeExecutor. The DB-dependent behaviour
 * (create task row on initial run, proceed on resume, expire on SLA breach)
 * is covered by feature tests.
 */
class TaskNodeExecutorTest extends TestCase
{
    private TaskNodeExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new TaskNodeExecutor;
    }

    #[Test]
    public function type_is_task_node(): void
    {
        $this->assertSame('task-node', $this->executor->type());
    }

    #[Test]
    public function category_is_action(): void
    {
        $this->assertSame(NodeCategory::Action, $this->executor->category());
    }
}
