<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\ControlFlowReducer;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

/**
 * Ensures splits and merges are correctly paired using graph reduction, preventing deadlock and
 * lack-of-synchronization. See {@see ControlFlowReducer} for the algorithm.
 */
class StructuredControlFlowVerificationRule implements VerificationRule
{
    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
        VerificationMode $mode = VerificationMode::Full,
    ): void {
        if ($graph->nodes() === []) {
            return;
        }

        $reduction = (new ControlFlowReducer($graph))->reduce();

        foreach ($reduction->mismatches as $mismatch) {
            $this->reportMismatch($mismatch, $result);
        }

        foreach ($reduction->unmatchedSplits as $split) {
            $this->reportUnmatchedSplit($split, $result);
        }

        foreach ($reduction->unmatchedMerges as $merge) {
            $result->addError(
                'merge.unmatched',
                'Merge node has no matching upstream split. Every merge must synchronize the branches of a single fork (parallel) or if/switch (conditional).',
                null,
                $merge['node'],
            );
        }
    }

    /**
     * @param  array{split:string,merge:string,type:string}  $mismatch
     */
    protected function reportMismatch(array $mismatch, WorkflowVerificationResult $result): void
    {
        if ($mismatch['type'] === 'deadlock') {
            $result->addError(
                'merge.deadlock',
                "Deadlock: a conditional split (if/switch) is joined by a parallel merge '{$mismatch['merge']}', which waits for branches that will never all execute. Use a conditional merge instead.",
                null,
                $mismatch['merge'],
            );

            return;
        }

        $result->addError(
            'merge.lack_of_synchronization',
            "Lack of synchronization: a parallel fork is joined by a conditional merge '{$mismatch['merge']}', which re-fires once per parallel branch. Use a parallel merge instead.",
            null,
            $mismatch['merge'],
        );
    }

    /**
     * @param  array{node:string,kind:string}  $split
     */
    protected function reportUnmatchedSplit(array $split, WorkflowVerificationResult $result): void
    {
        if ($split['kind'] === 'p-split') {
            $result->addError(
                'merge.fork_unjoined',
                'Fork node has no matching parallel merge. Every fork must converge on a parallel merge to synchronize its branches and avoid orphaned execution.',
                null,
                $split['node'],
            );

            return;
        }

        $result->addError(
            'merge.split_unsynchronized',
            'If/switch node has no matching conditional merge. Its branches must converge on a conditional merge before the flow continues.',
            null,
            $split['node'],
        );
    }
}
