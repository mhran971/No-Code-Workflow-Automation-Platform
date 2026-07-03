<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Workflows\Services\PublicFormService;

/**
 * Unauthenticated endpoints backing a workflow's public form-trigger link. See
 * {@see PublicFormService} for exactly what makes a workflow eligible to be served here.
 */
class PublicFormController extends Controller
{
    public function __construct(protected PublicFormService $service) {}

    public function show(string $publicToken): JsonResponse
    {
        $workflow = $this->service->findPublicForm($publicToken);

        if ($workflow === null) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        return response()->json($this->service->formSchema($workflow));
    }

    public function submit(Request $request, string $publicToken): JsonResponse
    {
        $workflow = $this->service->findPublicForm($publicToken);

        if ($workflow === null) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        $errors = $this->service->validateSubmission($workflow, $request->all());

        if ($errors !== []) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $errors], 422);
        }

        $this->service->submit($workflow, $request->all());

        // Deliberately no instance_id/status in the response — this caller is anonymous and
        // shouldn't receive internal execution details, unlike the authenticated trigger endpoints.
        return response()->json(['message' => 'Submitted successfully.'], 202);
    }
}
