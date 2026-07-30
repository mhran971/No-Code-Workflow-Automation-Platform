<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class ForkNodeTypeRule implements NodeTypeRule
{
    public function nodeType(): string
    {
        return 'and-node';
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
        $branchesPath = "nodes[{$index}].config.branches";

        $raw = is_array($config['branches'] ?? null) ? $config['branches'] : [];

        $validBranches = array_values(array_filter(
            $raw,
            fn ($b): bool => is_array($b)
                && is_string($b['name'] ?? null) && trim($b['name']) !== ''
                && is_string($b['key'] ?? null) && trim($b['key']) !== '',
        ));

        if ($validBranches === []) {
            $result->addError(
                'fork.branches_missing',
                'Fork node must have at least one branch with a name and key.',
                $branchesPath,
                $nodeId,
            );

            return;
        }

        $keys = array_column($validBranches, 'key');
        if (count($keys) !== count(array_unique($keys))) {
            $result->addError(
                'fork.branches_duplicate_keys',
                'Fork node branches must have unique keys.',
                $branchesPath,
                $nodeId,
            );
        }

        if ($nodeId !== null && $graph->hasNode($nodeId)) {
            $expected = count($validBranches);
            $actual = count($graph->outgoing($nodeId));

            if ($actual !== $expected) {
                $result->addError(
                    'fork.branches_mismatch',
                    "Fork node has {$actual} outgoing branch(es) but needs {$expected} (one per defined branch).",
                    null,
                    $nodeId,
                );
            }

            foreach ($graph->outgoing($nodeId) as $edge) {
                $joinKey = $edge['join_node_key'] ?? null;

                if (! is_string($joinKey) || trim($joinKey) === '') {
                    $result->addError(
                        'fork.join_node_key_missing',
                        'Every outgoing edge of a fork node must specify join_node_key pointing to its parallel merge.',
                        null,
                        $nodeId,
                        $edge['id'] ?? null,
                    );

                    continue;
                }

                if ($graph->hasNode($joinKey) && $graph->nodeType($joinKey) !== 'merge') {
                    $result->addError(
                        'fork.join_node_key_invalid',
                        "join_node_key '{$joinKey}' must reference a merge node.",
                        null,
                        $nodeId,
                        $edge['id'] ?? null,
                    );
                }
            }
        }
    }
}
