<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class SubWorkflowNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'sub_workflow';

    public function nodeType(): string
    {
        return 'sub-workflow';
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
        $path = "nodes[{$index}].config";

        $childWorkflowId = $config['workflowId'] ?? null;

        if ($childWorkflowId === null || $childWorkflowId === '' || ! is_numeric($childWorkflowId)) {
            $result->addError(
                'sub_workflow.workflow_id_missing',
                'Sub-workflow node must specify a valid workflowId.',
                "{$path}.workflowId",
                $nodeId,
            );

            return;
        }

        $childWorkflow = Workflow::query()->find((int) $childWorkflowId);

        if ($childWorkflow === null) {
            $result->addError(
                'sub_workflow.workflow_not_found',
                "Workflow #{$childWorkflowId} does not exist.",
                "{$path}.workflowId",
                $nodeId,
            );

            return;
        }

        if ($childWorkflow->current_version_id === null) {
            $result->addError(
                'sub_workflow.workflow_not_published',
                "Workflow #{$childWorkflowId} has not been published.",
                "{$path}.workflowId",
                $nodeId,
            );
        }

        if ($workflow !== null && (int) $childWorkflow->tenant_id !== (int) $workflow->tenant_id) {
            $result->addError(
                'sub_workflow.tenant_mismatch',
                "Sub-workflow #{$childWorkflowId} belongs to a different tenant.",
                "{$path}.workflowId",
                $nodeId,
            );
        }

        if ($workflow !== null && (int) $childWorkflow->id === (int) $workflow->id) {
            $result->addError(
                'sub_workflow.self_reference',
                'A workflow cannot reference itself as a sub-workflow.',
                "{$path}.workflowId",
                $nodeId,
            );
        }

        // Validate inputMapping template expressions (syntax + variable existence)
        $inputMapping = $config['inputMapping'] ?? [];
        if (is_array($inputMapping)) {
            foreach ($inputMapping as $childKey => $template) {
                if (! is_string($template)) {
                    continue;
                }

                // Detect unclosed template expressions (has {{ but no matching }})
                if (preg_match('/\{\{/', $template) && ! preg_match('/\{\{[^}]+\}\}/', $template)) {
                    $result->addError(
                        self::CODE_PREFIX.'.unclosed_template',
                        "Input mapping for \"{$childKey}\": Unclosed template expression. Use {{context.<key>}}.",
                        "{$path}.inputMapping.{$childKey}",
                        $nodeId,
                    );

                    continue;
                }

                $this->validateTemplateVariables(
                    $template,
                    "{$path}.inputMapping.{$childKey}",
                    $nodeId,
                    self::CODE_PREFIX,
                    $graph,
                    $result,
                );
            }
        }
    }
}
