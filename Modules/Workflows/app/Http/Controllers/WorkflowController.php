<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Workflows\Http\Requests\AiProposalRequest;
use Modules\Workflows\Http\Requests\PublishWorkflowRequest;
use Modules\Workflows\Http\Requests\StoreWorkflowRequest;
use Modules\Workflows\Http\Requests\UpdateDraftRequest;
use Modules\Workflows\Http\Requests\UpdateWorkflowStatusRequest;
use Modules\Workflows\Http\Requests\ValidateWorkflowDefinitionRequest;
use Modules\Workflows\Http\Resources\WorkflowValidationResultResource;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowTemplate;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\WorkflowManagementService;

class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowManagementService $workflowManagementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor();
        $workflows = $this->workflowManagementService->listVisibleWorkflows($actor, $request->query());

        return response()->json([
            'data' => $workflows->map(fn (Workflow $workflow) => $this->workflowManagementService->serializeWorkflow($workflow, $actor))->values(),
        ]);
    }

    public function templates(): JsonResponse
    {
        $templates = $this->workflowManagementService->listTemplates($this->actor());

        return response()->json([
            'data' => $templates->map(fn (WorkflowTemplate $template) => $this->serializeTemplate($template))->values(),
        ]);
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $workflow = $this->workflowManagementService->createWorkflow($this->actor(), $request->validated());

        return response()->json([
            'message' => 'Workflow created successfully.',
            'workflow' => $this->workflowManagementService->serializeWorkflow($workflow, $this->actor()),
        ], 201);
    }

    public function proposal(AiProposalRequest $request): JsonResponse
    {
        return response()->json(
            $this->workflowManagementService->generateAiProposal($this->actor(), $request->validated())
        );
    }

    public function validateDefinition(ValidateWorkflowDefinitionRequest $request): JsonResponse
    {
        $validation = $this->workflowManagementService->validateDefinition($request->validated()['definition']);

        return response()->json(
            (new WorkflowValidationResultResource($validation))->resolve($request)
        );
    }

    public function show(Workflow $workflow): JsonResponse
    {
        $workflow = $this->workflowManagementService->getVisibleWorkflow($this->actor(), $workflow);

        return response()->json([
            'data' => $this->workflowManagementService->serializeWorkflow($workflow, $this->actor(), true),
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
        $version = $this->workflowManagementService->publish($this->actor(), $workflow, $request->validated());
        $workflow->refresh();

        return response()->json([
            'workflow_id' => $workflow->id,
            'published_version' => $this->workflowManagementService->serializeVersion($version),
            'workflow_status' => $workflow->status?->value,
        ], 201);
    }

    public function versions(Workflow $workflow): JsonResponse
    {
        $versions = $this->workflowManagementService->listVersions($this->actor(), $workflow);

        return response()->json([
            'data' => $versions->map(fn (WorkflowVersion $version) => $this->workflowManagementService->serializeVersion($version))->values(),
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

    public function triggerWebhook(Request $request, Workflow $workflow): JsonResponse
    {
        $instance = $this->workflowManagementService->triggerWebhook($this->actor(), $workflow, $request->all());

        return response()->json([
            'instance_id' => $instance->id,
            'workflow_id' => $workflow->id,
            'version_number' => $workflow->current_version_number,
            'status' => $instance->status?->value,
        ], 201);
    }

    protected function actor(): User
    {
        /** @var User $actor */
        $actor = auth('api')->user();

        return $actor;
    }

    protected function serializeTemplate(WorkflowTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'category' => $template->category,
            'is_global' => $template->tenant_id === null,
            'usage_count' => $template->usage_count,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }
}
