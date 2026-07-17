<?php

namespace Modules\Workflows\Services;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;

class WorkflowDefinitionValidator
{
    public function __construct(
        protected WorkflowVerificationService $workflowVerificationService
    ) {}

    public function validate(array $definition, ?Workflow $workflow = null, ?User $actor = null, VerificationMode $mode = VerificationMode::Full): array
    {
        return $this->workflowVerificationService
            ->verify($definition, $workflow, $actor, $mode)
            ->toArray();
    }
}
