<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Workflows\Http\Requests\PublishWorkflowRequest;
use Modules\Workflows\Http\Requests\StoreWorkflowRequest;
use Modules\Workflows\Http\Requests\UpdateDraftRequest;
use Modules\Workflows\Http\Requests\UpdateWorkflowStatusRequest;
use Modules\Workflows\Http\Requests\ValidateWorkflowDefinitionRequest;
use Modules\Workflows\Http\Resources\WorkflowValidationResultResource;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\WorkflowVerificationService;
use Modules\Workflows\Services\WorkflowManagementService;
use Modules\Workflows\Services\WorkflowTemplateService;
use Modules\Workflows\Services\WorkflowVersioningService;
use Modules\Workflows\Transformers\WorkflowResource;
use Modules\Workflows\Transformers\WorkflowTemplateResource;
use Modules\Workflows\Transformers\WorkflowVersionResource;

class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowManagementService $workflowManagementService,
        protected WorkflowVerificationService $verificationService,
        protected WorkflowVersioningService $versioningService,
        protected WorkflowTemplateService $templateService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor();
        $workflows = $this->workflowManagementService->listVisibleWorkflows($actor, $request->query());

        return response()->json([
            'data' => WorkflowResource::collection($workflows),
        ]);
    }

    public function templates(): JsonResponse
    {
        $templates = $this->templateService->listTemplates($this->actor());

        return response()->json([
            'data' => WorkflowTemplateResource::collection($templates),
        ]);
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $workflow = $this->workflowManagementService->createWorkflow($this->actor(), $request->validated());

        return response()->json([
            'message' => 'Workflow created successfully.',
            'workflow' => WorkflowResource::make($workflow),
        ], 201);
    }

    public function validateDefinition(ValidateWorkflowDefinitionRequest $request): JsonResponse
    {
        $validation = $this->verificationService->verify($request->validated()['definition'])->toArray();

        return response()->json(
            (new WorkflowValidationResultResource($validation))->resolve($request)
        );
    }

    public function show(Workflow $workflow): JsonResponse
    {
        $workflow = $this->workflowManagementService->getVisibleWorkflow($this->actor(), $workflow);

        return response()->json([
            'data' => WorkflowResource::make($workflow, true),
        ]);
    }

    public function updateDraft(UpdateDraftRequest $request, Workflow $workflow): JsonResponse
    {
        $result = $this->workflowManagementService->updateDraft($this->actor(), $workflow, $request->validated());

        return response()->json([
            'workflow_id' => $workflow->id,
            'draft_revision' => $result['workflow']->draft_revision,
            'saved' => $result['saved'],
            'validation' => $result['validation'],
            'updated_at' => $result['workflow']->updated_at,
        ]);
    }

    public function publish(PublishWorkflowRequest $request, Workflow $workflow): JsonResponse
    {
        $version = $this->versioningService->publish($this->actor(), $workflow, $request->validated());
        $workflow->refresh();

        return response()->json([
            'workflow_id' => $workflow->id,
            'published_version' => WorkflowVersionResource::make($version),
            'workflow_status' => $workflow->status?->value,
        ], 201);
    }

    public function versions(Workflow $workflow): JsonResponse
    {
        $versions = $this->versioningService->listVersions($this->actor(), $workflow);

        return response()->json([
            'data' => WorkflowVersionResource::collection($versions),
        ]);
    }

    public function updateStatus(UpdateWorkflowStatusRequest $request, Workflow $workflow): JsonResponse
    {
        $workflow = $this->workflowManagementService->updateStatus(
            $this->actor(),
            $workflow,
            $request->validated()['status']
        );

        return response()->json([
            'workflow_id' => $workflow->id,
            'status' => $workflow->status?->value,
        ]);
    }

    public function destroy(Workflow $workflow): JsonResponse
    {
        $workflow = $this->workflowManagementService->softDelete($this->actor(), $workflow);

        return response()->json([
            'message' => 'Workflow deleted successfully.',
            'workflow_id' => $workflow->id,
            'status' => $workflow->status?->value,
        ]);
    }

    public function purge(Workflow $workflow): JsonResponse
    {
        $this->workflowManagementService->purge($this->actor(), $workflow);

        return response()->json([
            'message' => 'Workflow permanently deleted.',
        ]);
    }

    protected function actor(): User
    {
        /** @var User $actor */
        $actor = auth('api')->user();

        return $actor;
    }
}
