<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Integrations\Models\IntegrationConnection;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class ClickUpCreateTaskNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'clickup_create_task';

    public function nodeType(): string
    {
        return 'clickup-create-task';
    }

    public function verify(
        array $node,
        int $index,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
    ): void {
        $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        $workspaceId = trim((string) ($config['workspaceId'] ?? ''));
        if ($workspaceId === '') {
            $result->addError(
                'clickup_create_task.workspace_missing',
                'ClickUp: Create Task node must specify a workspace.',
                "nodes[{$index}].config.workspaceId",
                $nodeId,
            );
        }

        $listId = trim((string) ($config['listId'] ?? ''));
        if ($listId === '') {
            $result->addError(
                'clickup_create_task.list_missing',
                'ClickUp: Create Task node must specify a list.',
                "nodes[{$index}].config.listId",
                $nodeId,
            );
        }

        $name = trim((string) ($config['name'] ?? ''));
        if ($name === '') {
            $result->addError(
                'clickup_create_task.name_missing',
                'ClickUp: Create Task node must have a task name.',
                "nodes[{$index}].config.name",
                $nodeId,
            );
        } else {
            $this->validateTemplateVariables($name, "nodes[{$index}].config.name", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        $markdownContent = trim((string) ($config['markdownContent'] ?? ''));
        if ($markdownContent !== '') {
            $this->validateTemplateVariables($markdownContent, "nodes[{$index}].config.markdownContent", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        if ($workflow !== null) {
            $hasConnection = IntegrationConnection::query()
                ->where('integration_provider_id', 'clickup')
                ->where('tenant_id', $workflow->tenant_id)
                ->exists();

            if (! $hasConnection) {
                $result->addError(
                    'clickup_create_task.connection_missing',
                    'No ClickUp connection found for this tenant. Connect ClickUp via Integrations before publishing this workflow.',
                    null,
                    $nodeId,
                );
            }
        }
    }
}
