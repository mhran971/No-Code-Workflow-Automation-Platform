<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

interface VerificationRule
{
    /**
     * Whether this rule should be skipped when verifying a segment
     * (a mid-execution sub-flow with no trigger and no saved workflow).
     *
     * Segments are dynamically designed sub-flows submitted by a manager
     * when a workflow pauses at a `dynamic-flow` node. They have only
     * nodes + edges — no trigger, no workflow context. Rules that
     * depend on a trigger or saved workflow should return true.
     *
     * Rules that return true are excluded from the pipeline when
     * VerificationMode::Segment is active.
     */
    public function skipForSegment(): bool;

    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
        VerificationMode $mode = VerificationMode::Full,
    ): void;
}
