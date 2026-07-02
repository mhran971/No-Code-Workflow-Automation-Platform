<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Workflows\Http\Requests\DeleteTaskCommentRequest;
use Modules\Workflows\Http\Requests\ListTaskCommentsRequest;
use Modules\Workflows\Http\Requests\StoreTaskCommentRequest;
use Modules\Workflows\Http\Resources\WorkflowInstanceCommentResource;
use Modules\Workflows\Models\WorkflowTask;
use Modules\Workflows\Services\WorkflowCommentsService;

class MobileTaskCommentsController extends Controller
{
    public function __construct(private readonly WorkflowCommentsService $service) {}

    public function index(ListTaskCommentsRequest $request, WorkflowTask $task): AnonymousResourceCollection
    {
        $comments = $this->service->listForTask($task, $this->actor(), (int) $request->input('per_page', 20));

        return WorkflowInstanceCommentResource::collection($comments);
    }

    public function store(StoreTaskCommentRequest $request, WorkflowTask $task): JsonResponse
    {
        $comment = $this->service->createForTask($task, $this->actor(), (string) $request->input('body'));

        return (new WorkflowInstanceCommentResource($comment))->response()->setStatusCode(201);
    }

    public function destroy(DeleteTaskCommentRequest $request, WorkflowTask $task, int $comment): JsonResponse
    {
        $deleted = $this->service->deleteForTask($task, $this->actor(), $comment);

        if (! $deleted) {
            return response()->json(['message' => 'Comment not found.'], 404);
        }

        return response()->json(['message' => 'Comment deleted.']);
    }

    private function actor()
    {
        return auth('api')->user();
    }
}
