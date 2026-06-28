<?php

namespace Modules\Workflows\Services\Execution;

use Illuminate\Support\Facades\Cache;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\Verification\WorkflowDefinitionNormalizer;

/**
 * Turns a raw workflow definition into an {@see ExecutionPlan}. For published versions (whose definition is
 * immutable) the normalized form is cached by version id.
 */
class ExecutionPlanCompiler
{
    public function __construct(protected WorkflowDefinitionNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public function compile(array $definition): ExecutionPlan
    {
        return new ExecutionPlan($this->normalizer->normalize($definition));
    }

    /**
     * Compile the plan for a published version, caching the (expensive) normalization keyed by the immutable
     * version id.
     */
    public function compileVersion(WorkflowVersion $version): ExecutionPlan
    {
        $normalized = Cache::rememberForever(
            "workflows:plan:{$version->id}",
            fn (): array => $this->normalizer->normalize($version->definition ?? []),
        );

        return new ExecutionPlan($normalized);
    }
}
