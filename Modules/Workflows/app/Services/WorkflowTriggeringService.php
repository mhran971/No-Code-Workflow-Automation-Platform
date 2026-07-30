<?php

namespace Modules\Workflows\Services;

use App\Services\BaseService;
use Modules\Auth\Models\User;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\WorkflowDispatcher;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkflowTriggeringService extends BaseService
{
    public function __construct(
        protected WorkflowAuthorizationService $authorizationService,
        protected WorkflowDispatcher $dispatcher,
    ) {}

    public function triggerWebhook(User $actor, Workflow $workflow, array $payload = []): WorkflowInstance
    {
        $this->authorizationService->assertCanView($actor, $workflow);

        if (in_array($workflow->status, [WorkflowStatus::Disabled, WorkflowStatus::Deleted], true)) {
            throw new HttpException(410, 'Workflow is not available for triggering.');
        }

        return $this->dispatcher->dispatch($workflow, TriggerType::Webhook, $payload);
    }

    public function triggerManual(User $actor, Workflow $workflow, array $payload = []): WorkflowInstance
    {
        $this->authorizationService->assertCanView($actor, $workflow);

        if (in_array($workflow->status, [WorkflowStatus::Disabled, WorkflowStatus::Deleted], true)) {
            throw new HttpException(410, 'Workflow is not available for triggering.');
        }

        return $this->dispatcher->dispatch($workflow, TriggerType::Manual, $payload);
    }
}
