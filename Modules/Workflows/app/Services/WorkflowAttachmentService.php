<?php

namespace Modules\Workflows\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\User;
use Modules\Workflows\Models\WorkflowInstanceAttachment;
use Modules\Workflows\Repositories\WorkflowInstanceAttachmentRepository;

class WorkflowAttachmentService
{
    public function __construct(
        private readonly WorkflowInstanceContextService $contextService,
        private readonly WorkflowInstanceAttachmentRepository $repository,
    ) {}

    public function listForTask($task, User $actor, int $perPage = 20)
    {
        $instance = $this->contextService->resolveFromTask($task, $actor);

        return $this->repository->listByWorkflowInstance($instance->id, $perPage);
    }

    public function uploadForTask($task, User $actor, UploadedFile $file): WorkflowInstanceAttachment
    {
        $instance = $this->contextService->resolveFromTask($task, $actor);

        $disk = Storage::disk('public');
        $directory = 'workflow-attachments/'.$instance->id;
        $path = $file->store($directory, 'public');

        try {
            return DB::transaction(function () use ($instance, $actor, $file, $path): WorkflowInstanceAttachment {
                return $this->repository->create([
                    'workflow_instance_id' => $instance->id,
                    'uploaded_by' => $actor->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?: 0,
                    'created_at' => now(),
                ]);
            });
        } catch (\Throwable $throwable) {
            $disk->delete($path);

            throw $throwable;
        }
    }

    public function deleteForTask($task, User $actor, int $attachmentId): bool
    {
        $instance = $this->contextService->resolveFromTask($task, $actor);
        $attachment = WorkflowInstanceAttachment::query()
            ->whereKey($attachmentId)
            ->where('workflow_instance_id', $instance->id)
            ->first();

        if (! $attachment) {
            return false;
        }

        $deleted = $this->repository->deleteByIdAndWorkflowInstance($attachmentId, $instance->id);

        if ($deleted) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        return $deleted;
    }
}
