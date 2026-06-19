<?php

namespace Modules\Workflows\Services\Verification;

/**
 * The outcome of {@see ControlFlowReducer::reduce()}.
 */
class ControlFlowReductionResult
{
    /**
     * @param  list<array{split:string,merge:string,splitKind:string,mergeKind:string}>  $matched
     * @param  list<array{split:string,merge:string,type:string}>  $mismatches
     * @param  list<array{node:string,kind:string}>  $unmatchedSplits
     * @param  list<array{node:string,kind:string}>  $unmatchedMerges
     */
    public function __construct(
        public readonly array $matched,
        public readonly array $mismatches,
        public readonly array $unmatchedSplits,
        public readonly array $unmatchedMerges,
    ) {}
}
