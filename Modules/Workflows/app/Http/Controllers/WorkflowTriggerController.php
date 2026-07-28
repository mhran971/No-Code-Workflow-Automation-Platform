<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\PublicFormService;
use Modules\Workflows\Services\WorkflowTriggeringService;

class WorkflowTriggerController extends Controller
{
    public function __construct(
        protected WorkflowTriggeringService $service,
        protected PublicFormService $publicFormService,
    ) {}

    public function manual(Request $request, Workflow $workflow): JsonResponse
    {
        $instance = $this->service->triggerManual(auth('api')->user(), $workflow, $request->all());

        return response()->json(['instance_id' => $instance->id, 'status' => $instance->status], 202);
    }

    public function triggerWebhook(Request $request, Workflow $workflow): JsonResponse
    {
        $instance = $this->service->triggerWebhook(auth('api')->user(), $workflow, $request->all());

        return response()->json([
            'instance_id' => $instance->id,
            'workflow_id' => $workflow->id,
            'version_number' => $workflow->current_version_number,
            'status' => $instance->status?->value,
        ], 201);
    }

    public function showForm(string $publicToken): JsonResponse
    {
        $workflow = $this->publicFormService->findPublicForm($publicToken);

        if ($workflow === null) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        return response()->json($this->publicFormService->formSchema($workflow));
    }

    public function submitForm(Request $request, string $publicToken): JsonResponse
    {
        $workflow = $this->publicFormService->findPublicForm($publicToken);

        if ($workflow === null) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        $errors = $this->publicFormService->validateSubmission($workflow, $request->all());

        if ($errors !== []) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $errors], 422);
        }

        $this->publicFormService->submit($workflow, $request->all());

        // Deliberately no instance_id/status in the response — this caller is anonymous and
        // shouldn't receive internal execution details, unlike the authenticated trigger endpoints.
        return response()->json(['message' => 'Submitted successfully.'], 202);
    }
}
