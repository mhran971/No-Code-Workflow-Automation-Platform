<?php

namespace Modules\Workflows\Services;

use Modules\Auth\Models\User;
use Modules\Workflows\Models\WorkflowInstanceComment;
use Modules\Workflows\Repositories\WorkflowInstanceCommentRepository;

class WorkflowCommentsService
{
    public function __construct(
        private readonly WorkflowInstanceContextService $contextService,
        private readonly WorkflowInstanceCommentRepository $repository,
    ) {}

    public function listForTask($task, User $actor, int $perPage = 20)
    {
        $instance = $this->contextService->resolveFromTask($task, $actor);

        return $this->repository->listByWorkflowInstance($instance->id, $perPage);
    }

    public function createForTask($task, User $actor, string $body): WorkflowInstanceComment
    {
        $instance = $this->contextService->resolveFromTask($task, $actor);

        return $this->repository->create([
            'workflow_instance_id' => $instance->id,
            'user_id' => $actor->id,
            'body' => $body,
        ]);
    }

    public function deleteForTask($task, User $actor, int $commentId): bool
    {
        $instance = $this->contextService->resolveFromTask($task, $actor);

        return $this->repository->softDeleteByIdAndWorkflowInstance($commentId, $instance->id);
    }
}
