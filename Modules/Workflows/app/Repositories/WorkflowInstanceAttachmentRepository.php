<?php

namespace Modules\Workflows\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Workflows\Models\WorkflowInstanceAttachment;

class WorkflowInstanceAttachmentRepository
{
    public function listByWorkflowInstance(int $workflowInstanceId, int $perPage = 20): LengthAwarePaginator
    {
        return WorkflowInstanceAttachment::query()
            ->where('workflow_instance_id', $workflowInstanceId)
            ->with('uploadedBy')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): WorkflowInstanceAttachment
    {
        return WorkflowInstanceAttachment::create($data);
    }

    public function deleteByIdAndWorkflowInstance(int $attachmentId, int $workflowInstanceId): bool
    {
        return (bool) WorkflowInstanceAttachment::query()
            ->whereKey($attachmentId)
            ->where('workflow_instance_id', $workflowInstanceId)
            ->delete();
    }
}
