<?php

namespace Modules\Workflows\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Workflows\Models\WorkflowInstanceComment;

class WorkflowInstanceCommentRepository
{
    public function listByWorkflowInstance(int $workflowInstanceId, int $perPage = 20): LengthAwarePaginator
    {
        return WorkflowInstanceComment::query()
            ->where('workflow_instance_id', $workflowInstanceId)
            ->with('author')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    public function create(array $data): WorkflowInstanceComment
    {
        return WorkflowInstanceComment::create($data);
    }

    public function softDeleteByIdAndWorkflowInstance(int $commentId, int $workflowInstanceId): bool
    {
        return (bool) WorkflowInstanceComment::query()
            ->whereKey($commentId)
            ->where('workflow_instance_id', $workflowInstanceId)
            ->delete();
    }
}
