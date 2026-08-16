<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Services\ClickUp\ClickUpClient;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

class ClickUpCreateTaskExecutor implements NodeExecutor
{
    public function __construct(protected ClickUpClient $clickUp) {}

    public function type(): string
    {
        return 'clickup-create-task';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Action;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $config = $context->config();

        $listId = trim((string) ($config['listId'] ?? ''));
        $name = $context->render((string) ($config['name'] ?? ''));
        $markdownContent = isset($config['markdownContent']) && $config['markdownContent'] !== ''
            ? $context->render((string) $config['markdownContent'])
            : null;

        if ($listId === '' || $name === '') {
            return NodeExecutionResult::fail('ClickUp: Create Task node is missing its list or task name configuration.', false);
        }

        $connection = IntegrationConnection::query()
            ->where('integration_provider_id', 'clickup')
            ->where('tenant_id', $context->instance()->tenant_id)
            ->first();

        if ($connection === null) {
            return NodeExecutionResult::fail(
                'No ClickUp connection found for this tenant. Connect ClickUp via Integrations to enable task creation.',
                retryable: false,
            );
        }

        try {
            $task = $this->clickUp->createTask($connection, $listId, $name, $markdownContent);
        } catch (IntegrationException $e) {
            return NodeExecutionResult::fail($e->getMessage(), retryable: $e->status() >= 500 || $e->status() === 429);
        }

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['clickup_task_id' => $task['id'] ?? null, 'clickup_task_url' => $task['url'] ?? null],
        );
    }
}
