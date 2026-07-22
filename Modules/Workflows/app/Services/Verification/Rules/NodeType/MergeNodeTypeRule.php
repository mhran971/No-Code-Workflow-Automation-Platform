<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class MergeNodeTypeRule implements NodeTypeRule
{
    public const VALID_MODES = ['parallel', 'conditional'];

    public const MIN_BRANCHES = 2;

    public function nodeType(): string
    {
        return 'merge';
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

        $this->verifyMode($config, $index, $nodeId, $result);
        $this->verifyBranchCount($config, $index, $nodeId, $graph, $result);
    }

    protected function verifyMode(
        array $config,
        int $index,
        ?string $nodeId,
        WorkflowVerificationResult $result,
    ): void {
        $modePath = "nodes[{$index}].config.mergeMode";
        $mode = trim((string) ($config['mergeMode'] ?? ''));

        if ($mode === '') {
            $result->addError('merge.mode_missing', 'Merge node must specify a merge mode.', $modePath, $nodeId);

            return;
        }

        if (! in_array($mode, self::VALID_MODES, true)) {
            $result->addError(
                'merge.mode_invalid',
                "Merge node mode must be 'parallel' or 'conditional', got '{$mode}'.",
                $modePath,
                $nodeId,
            );
        }
    }

    protected function verifyBranchCount(
        array $config,
        int $index,
        ?string $nodeId,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
    ): void {
        $countPath = "nodes[{$index}].config.branchCount";
        $raw = $config['branchCount'] ?? null;

        if ($raw === null || $raw === '' || ! is_numeric($raw) || (int) $raw != $raw) {
            $result->addError(
                'merge.branch_count_missing',
                'Merge node must specify the number of incoming branches.',
                $countPath,
                $nodeId,
            );

            return;
        }

        $expected = (int) $raw;

        if ($expected < self::MIN_BRANCHES) {
            $result->addError(
                'merge.branch_count_invalid',
                'Merge node must have at least '.self::MIN_BRANCHES.' incoming branches.',
                $countPath,
                $nodeId,
            );

            return;
        }

        if ($nodeId !== null && $graph->hasNode($nodeId)) {
            $actual = count($graph->incoming($nodeId));

            if ($actual !== $expected) {
                $result->addError(
                    'merge.branches_mismatch',
                    "Merge node has {$actual} incoming edge(s) but declares {$expected} branch(es). Wire one branch per declared incoming branch.",
                    null,
                    $nodeId,
                );
            }
        }
    }
}
