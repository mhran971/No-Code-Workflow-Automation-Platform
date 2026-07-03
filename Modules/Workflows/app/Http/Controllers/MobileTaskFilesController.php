<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Workflows\Http\Requests\DeleteTaskFileRequest;
use Modules\Workflows\Http\Requests\ListTaskFilesRequest;
use Modules\Workflows\Http\Requests\UploadTaskFileRequest;
use Modules\Workflows\Http\Resources\WorkflowInstanceAttachmentResource;
use Modules\Workflows\Models\WorkflowTask;
use Modules\Workflows\Services\WorkflowAttachmentService;

class MobileTaskFilesController extends Controller
{
    public function __construct(private readonly WorkflowAttachmentService $service) {}

    public function index(ListTaskFilesRequest $request, WorkflowTask $task): AnonymousResourceCollection
    {
        // Handle cases where route-model binding could not find the task.
        // In that case Laravel would normally throw a 404 before reaching this method,
        // but we keep a defensive check for any custom binding/override scenarios.
        if (! $task || ! $task->exists) {
            abort(response()->json(['message' => 'Task not found.'], 404));
        }

        $attachments = $this->service->listForTask($task, $this->actor(), (int) $request->input('per_page', 20));

        return WorkflowInstanceAttachmentResource::collection($attachments);
    }


    public function store(UploadTaskFileRequest $request, WorkflowTask $task): JsonResponse
    {
        $attachment = $this->service->uploadForTask($task, $this->actor(), $request->file('file'));

        return (new WorkflowInstanceAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function destroy(DeleteTaskFileRequest $request, WorkflowTask $task, int $attachment): JsonResponse
    {
        $deleted = $this->service->deleteForTask($task, $this->actor(), $attachment);

        if (! $deleted) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return response()->json(['message' => 'Attachment deleted.']);
    }

    private function actor()
    {
        return auth('api')->user();
    }
}
