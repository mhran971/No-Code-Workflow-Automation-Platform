<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\DataFlowResult;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\DataFlowAnalyzer;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

/**
 * Catches data hazards at validation time using {@see DataFlowAnalyzer}:
 *  - parallel merges where concurrent branches write the same variable (data loss);
 *  - nodes that read a `context.*` variable which is produced somewhere in the workflow but is not
 *    guaranteed on every path reaching the reader (missing-data conflict).
 *
 * Fields holding produced variables (`outputVariables`, `outputVariable`) are treated as writes, not
 * reads. References to variables that exist nowhere in the workflow are left to the node-type rules.
 */
class DataFlowVerificationRule implements VerificationRule
{
    private const WRITE_FIELDS = ['outputVariables', 'outputVariable'];

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

        $dataFlow = (new DataFlowAnalyzer($graph))->analyze();

        $this->reportConflicts($dataFlow, $result);
        $this->reportMissingReads($graph, $dataFlow, $result);
        $this->reportMergeOutputAvailability($graph, $dataFlow, $result);
    }

    protected function reportConflicts(DataFlowResult $dataFlow, WorkflowVerificationResult $result): void
    {
        foreach ($dataFlow->conflicts as $conflict) {
            $result->addError(
                'dataflow.parallel_conflict',
                "Variable '{$conflict['variable']}' is written by more than one parallel branch arriving at this merge. One branch's value will overwrite the other (data loss). Rename the outputs or merge them explicitly.",
                null,
                $conflict['node'],
            );
        }
    }

    protected function reportMissingReads(
        WorkflowDefinitionGraph $graph,
        DataFlowResult $dataFlow,
        WorkflowVerificationResult $result,
    ): void {
        $triggerNodeId = $graph->triggerNodeId();

        foreach ($graph->nodes() as $node) {
            $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;

            if ($nodeId === null || $nodeId === $triggerNodeId) {
                continue;
            }

            $guaranteed = $dataFlow->guaranteedAt($nodeId);

            foreach ($this->readsOf($node) as $variable) {
                // Only flag variables the workflow actually produces somewhere; truly unknown
                // variables are the node-type rules' responsibility.
                if (! $dataFlow->isProduced($variable) || in_array($variable, $guaranteed, true)) {
                    continue;
                }

                $result->addError(
                    'dataflow.variable_missing',
                    "Variable '{$variable}' may be unset here: it is only produced on some of the paths that reach this node. Ensure it is set on every incoming path before it is read.",
                    null,
                    $nodeId,
                );
            }
        }
    }

    /**
     * A merge node forwards its declared output variables downstream, so each must actually be
     * produced by the branches it joins:
     *  - conditional merge: only one branch runs, so the variable must be produced on **every**
     *    incoming branch (i.e. guaranteed at the merge);
     *  - parallel merge: all branches run, so it is enough that **at least one** produces it (which
     *    is also captured by the guaranteed set, a union for parallel merges).
     */
    protected function reportMergeOutputAvailability(
        WorkflowDefinitionGraph $graph,
        DataFlowResult $dataFlow,
        WorkflowVerificationResult $result,
    ): void {
        foreach ($graph->nodes() as $node) {
            if (($node['type'] ?? null) !== 'merge') {
                continue;
            }

            $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
            if ($nodeId === null) {
                continue;
            }

            $config = is_array($node['config'] ?? null) ? $node['config'] : [];
            $mode = trim((string) ($config['mergeMode'] ?? ''));
            $declared = is_array($config['outputVariables'] ?? null) ? $config['outputVariables'] : [];
            $guaranteed = $dataFlow->guaranteedAt($nodeId);

            foreach ($declared as $raw) {
                if (! is_string($raw) || trim($raw) === '') {
                    continue;
                }

                $variable = $this->normalizeVariable($raw);

                if (in_array($variable, $guaranteed, true)) {
                    continue;
                }

                if ($mode === 'conditional') {
                    $result->addError(
                        'merge.output_not_on_all_branches',
                        "Output variable '{$variable}' is not produced on every incoming branch. A conditional merge runs only one branch, so it cannot guarantee '{$variable}' downstream.",
                        null,
                        $nodeId,
                    );
                } else {
                    $result->addError(
                        'merge.output_not_on_any_branch',
                        "Output variable '{$variable}' is not produced on any incoming branch reaching this merge.",
                        null,
                        $nodeId,
                    );
                }
            }
        }
    }

    protected function normalizeVariable(string $key): string
    {
        $key = trim($key);
        $bare = str_starts_with($key, 'context.') ? substr($key, strlen('context.')) : $key;

        return 'context.'.$bare;
    }

    /**
     * Extract the distinct `context.*` variables a node reads from its config (excluding the fields
     * that declare produced variables).
     *
     * @return list<string>
     */
    protected function readsOf(array $node): array
    {
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        $strings = [];
        foreach ($config as $key => $value) {
            if (in_array($key, self::WRITE_FIELDS, true)) {
                continue;
            }

            $this->collectStrings($value, $strings);
        }

        $variables = [];
        foreach ($strings as $string) {
            if (preg_match_all('/context\.[A-Za-z0-9_]+/', $string, $matches)) {
                foreach ($matches[0] as $match) {
                    $variables[$match] = true;
                }
            }
        }

        return array_keys($variables);
    }

    /**
     * @param  list<string>  $out
     */
    protected function collectStrings(mixed $value, array &$out): void
    {
        if (is_string($value)) {
            $out[] = $value;

            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectStrings($item, $out);
            }
        }
    }
}
