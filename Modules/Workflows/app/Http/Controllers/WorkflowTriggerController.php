<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\WorkflowTriggeringService;

class WorkflowTriggerController extends Controller
{
    public function __construct(protected WorkflowTriggeringService $service) {}

    public function manual(Request $request, Workflow $workflow): JsonResponse
    {
        $instance = $this->service->triggerManual($this->actor(), $workflow, $request->all());

        return response()->json(['instance_id' => $instance->id, 'status' => $instance->status], 202);
    }

    public function form(Request $request, Workflow $workflow): JsonResponse
    {
        $instance = $this->service->triggerForm($this->actor(), $workflow, $request->all());

        return response()->json(['instance_id' => $instance->id, 'status' => $instance->status], 202);
    }

    public function triggerWebhook(Request $request, Workflow $workflow): JsonResponse
    {
        $instance = $this->service->triggerWebhook($this->actor(), $workflow, $request->all());

        return response()->json([
            'instance_id' => $instance->id,
            'workflow_id' => $workflow->id,
            'version_number' => $workflow->current_version_number,
            'status' => $instance->status?->value,
        ], 201);
    }

    protected function actor(): User
    {
        return auth('api')->user();
    }
}
