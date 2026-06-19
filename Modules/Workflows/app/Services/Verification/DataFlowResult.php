<?php

namespace Modules\Workflows\Services\Verification;

/**
 * The outcome of {@see DataFlowAnalyzer::analyze()}.
 */
class DataFlowResult
{
    /**
     * @param  array<string, list<string>>  $guaranteed  nodeId => context keys available on every path into the node
     * @param  array<string, list<string>>  $possible  nodeId => context keys available on at least one path into the node
     * @param  list<array{node:string,variable:string}>  $conflicts  concurrent writes to the same variable at a parallel merge
     * @param  list<string>  $producers  every context key produced anywhere in the workflow
     */
    public function __construct(
        public readonly array $guaranteed,
        public readonly array $possible,
        public readonly array $conflicts,
        public readonly array $producers,
    ) {}

    /**
     * @return list<string>
     */
    public function guaranteedAt(string $nodeId): array
    {
        return $this->guaranteed[$nodeId] ?? [];
    }

    public function isProduced(string $variable): bool
    {
        return in_array($variable, $this->producers, true);
    }
}
