<?php

namespace Modules\Workflows\Services\Execution;

use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\Exceptions\UnsupportedNodeTypeException;

/**
 * Node-type → executor lookup. Executors are registered in WorkflowsServiceProvider, exactly like the
 * verification NodeTypeRule registry. An unknown type fails fast.
 */
class NodeExecutorRegistry
{
    /** @var array<string, NodeExecutor> */
    protected array $executors = [];

    public function register(NodeExecutor $executor): self
    {
        $this->executors[$executor->type()] = $executor;

        return $this;
    }

    public function registerAs(string $type, NodeExecutor $executor): self
    {
        $this->executors[$type] = $executor;

        return $this;
    }

    public function has(string $type): bool
    {
        return isset($this->executors[$type]);
    }

    public function for(string $type): NodeExecutor
    {
        return $this->executors[$type] ?? throw UnsupportedNodeTypeException::for($type);
    }

    /**
     * @return list<string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->executors);
    }
}
