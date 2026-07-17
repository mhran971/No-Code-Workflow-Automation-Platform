<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Team\Models\Team;
use Modules\Team\Repositories\AuditTrailRepository;
use Modules\Workflows\Enums\DynamicFlowStatus;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowDynamicFlow;
use Modules\Workflows\Models\WorkflowEvent;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\WorkflowDispatcher;
use Modules\Workflows\Services\WorkflowDefinitionValidator;

class DynamicFlowController extends Controller
{
    public function __construct(
        protected WorkflowDefinitionValidator $validator,
        protected WorkflowDispatcher $dispatcher,
        protected AuditTrailRepository $auditTrail,
    ) {}

    public function show(Request $request, WorkflowInstance $instance): JsonResponse
    {
        $actor = $request->user();
        $this->assertCanDesignDynamicFlow($actor, $instance);

        $dynamicFlow = WorkflowDynamicFlow::query()
            ->where('instance_id', $instance->id)
            ->where('status', DynamicFlowStatus::AwaitingDesign)
            ->firstOrFail();

        $parentWorkflow = $instance->workflow;
        $parentDefinition = $parentWorkflow->draft_definition ?? null;

        return response()->json([
            'dynamic_flow' => [
                'id' => $dynamicFlow->id,
                'status' => $dynamicFlow->status->value,
                'node_key' => $dynamicFlow->node_key,
                'instance_id' => $dynamicFlow->instance_id,
            ],
            'parent_definition' => $parentDefinition,
        ]);
    }

    public function storeDefinition(Request $request, WorkflowInstance $instance): JsonResponse
    {
        $actor = $request->user();
        $this->assertCanDesignDynamicFlow($actor, $instance);

        $dynamicFlow = WorkflowDynamicFlow::query()
            ->where('instance_id', $instance->id)
            ->where('status', DynamicFlowStatus::AwaitingDesign)
            ->firstOrFail();

        $request->validate([
            'definition' => 'required|array',
            'definition.nodes' => 'required|array|min:1',
            'definition.edges' => 'required|array',
        ]);

        $definition = $request->input('definition');
        $workflow = $instance->workflow;

        $errors = $this->validator->validate($definition, $workflow, $actor, VerificationMode::Segment);

        if (! empty($errors['errors'])) {
            return response()->json(['errors' => $errors['errors']], 422);
        }

        $dynamicFlow->update([
            'created_by_id' => $actor->id,
        ]);

        try {
            $childInstance = $this->dispatcher->dispatch(
                $workflow,
                TriggerType::SubWorkflow,
                [],
                null,
                (int) $instance->id,
                (int) $dynamicFlow->execution_id,
                $definition,
            );
        } catch (\Throwable $e) {
            return response()->json(['errors' => [$e->getMessage()]], 422);
        }

        $dynamicFlow->update([
            'definition' => $definition,
            'status' => DynamicFlowStatus::Executing,
            'child_instance_id' => $childInstance->id,
        ]);

        $instance->update([
            'status' => WorkflowInstanceStatus::Running,
            'paused_reason' => null,
        ]);

        WorkflowEvent::create([
            'instance_id' => $instance->id,
            'tenant_id' => $instance->tenant_id,
            'node_key' => $dynamicFlow->node_key,
            'type' => 'dynamic_flow.created',
            'payload' => [
                'dynamic_flow_id' => $dynamicFlow->id,
                'child_instance_id' => $childInstance->id,
                'designed_by' => $actor->id,
            ],
        ]);

        $this->auditTrail->create([
            'tenant_id' => $actor->tenant_id,
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'dynamic_flow_created',
            'subject_type' => Workflow::class,
            'subject_id' => $workflow->id,
            'metadata' => [
                'instance_id' => $instance->id,
                'dynamic_flow_id' => $dynamicFlow->id,
                'node_key' => $dynamicFlow->node_key,
                'child_instance_id' => $childInstance->id,
            ],
        ]);

        return response()->json([
            'dynamic_flow' => [
                'id' => $dynamicFlow->id,
                'status' => $dynamicFlow->status->value,
                'child_instance_id' => $childInstance->id,
            ],
        ], 201);
    }

    protected function assertCanDesignDynamicFlow($actor, WorkflowInstance $instance): void
    {
        $workflow = $instance->workflow;

        if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
            abort(403, 'Not authorized.');
        }

        if ((int) $actor->id === (int) $workflow->created_by_id) {
            return;
        }

        $team = Team::query()
            ->where('tenant_id', (int) $actor->tenant_id)
            ->where('manager_id', (int) $actor->id)
            ->first();

        if ($team !== null && (int) $workflow->team_id === (int) $team->id) {
            return;
        }

        abort(403, 'Only the workflow creator or a team manager can design a dynamic flow.');
    }
}
