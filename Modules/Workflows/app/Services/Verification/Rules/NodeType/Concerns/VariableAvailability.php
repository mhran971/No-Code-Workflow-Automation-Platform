<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType\Concerns;

use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

trait VariableAvailability
{
    protected function validateVariableNamespace(
        string $variable,
        string $codePrefix,
        string $path,
        ?string $nodeId,
        WorkflowVerificationResult $result,
    ): bool {
        if (! str_starts_with($variable, 'context.') && ! str_starts_with($variable, 'customer.')) {
            $result->addError(
                "{$codePrefix}.variable_invalid_namespace",
                "Variable '{$variable}' must be prefixed with 'context.' or 'customer.' (e.g. context.status).",
                $path,
                $nodeId,
            );

            return false;
        }

        return true;
    }

    protected function validateContextVariableExists(
        string $variable,
        array $available,
        string $codePrefix,
        string $path,
        ?string $nodeId,
        WorkflowVerificationResult $result,
    ): void {
        if (! in_array($variable, $available, true)) {
            $hint = $available !== []
                ? ' Available: '.implode(', ', $available).'.'
                : ' No context variables are defined by the trigger.';

            $result->addError(
                "{$codePrefix}.variable_undefined",
                "Variable '{$variable}' is not defined in the workflow context.{$hint}",
                $path,
                $nodeId,
            );
        }
    }

    protected function warnIfParallelPaths(
        string $nodeId,
        string $codePrefix,
        string $path,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
    ): void {
        if ($this->hasParallelPaths($nodeId, $graph)) {
            $result->addWarning(
                "{$codePrefix}.parallel_paths",
                'This node is reachable via multiple execution paths. Variables set by intermediate nodes on a specific branch may not be available when execution arrives via a different branch.',
                $path,
                $nodeId,
            );
        }
    }

    /**
     * Collect context.* variable keys available at $nodeId via the trigger.
     * Returns null when the available keys cannot be determined (unsupported trigger type).
     *
     * @return list<string>|null
     */
    protected function collectAvailableContextKeys(?string $nodeId, WorkflowDefinitionGraph $graph): ?array
    {
        if ($nodeId === null) {
            return null;
        }

        $definition = $graph->definition();
        $triggerType = $definition['trigger']['type'] ?? null;

        if ($triggerType !== 'manual-trigger') {
            return null;
        }

        $triggerNodeId = $graph->triggerNodeId();

        if ($triggerNodeId === null || ! in_array($triggerNodeId, $graph->ancestorNodeIds($nodeId), true)) {
            return null;
        }

        $rawVariables = $definition['trigger']['config']['variables'] ?? [];

        if (! is_array($rawVariables)) {
            return [];
        }

        $keys = [];
        foreach ($rawVariables as $var) {
            if (is_array($var) && isset($var['key']) && is_string($var['key']) && $var['key'] !== '') {
                $keys[] = 'context.'.$var['key'];
            }
        }

        return $keys;
    }

    protected function hasParallelPaths(string $nodeId, WorkflowDefinitionGraph $graph): bool
    {
        foreach ($graph->ancestorNodeIds($nodeId) as $ancestorId) {
            $outgoing = $graph->outgoing($ancestorId);

            if (count($outgoing) <= 1) {
                continue;
            }

            $pathsLeadingToNode = 0;

            foreach ($outgoing as $edge) {
                $target = $edge['target_node_key'] ?? null;

                if (! is_string($target)) {
                    continue;
                }

                if ($target === $nodeId || in_array($nodeId, $graph->reachableFrom($target), true)) {
                    $pathsLeadingToNode++;
                }
            }

            if ($pathsLeadingToNode > 1) {
                return true;
            }
        }

        return false;
    }
}
