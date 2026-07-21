<?php

namespace Modules\Workflows\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\WorkflowAuthorizationService;

class WorkflowResource extends JsonResource
{
    /** @var bool */
    protected $includeDetails;

    public function __construct(Workflow $resource, $includeDetails = false)
    {
        parent::__construct($resource);
        $this->includeDetails = $includeDetails;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'public_token' => $this->public_token,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value,
            'team' => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null,
            'version_number' => $this->current_version_number,
            'version_label' => $this->current_version_label,
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'total_runs' => $this->total_runs,
            'active_instances' => $this->active_instances,
            'actions' => $this->availableActions(auth('api')->user(), $this->resource),
        ];

        if ($this->includeDetails) {
            $payload['draft_revision'] = $this->draft_revision;
            $payload['draft_definition'] = $this->draft_definition;
            $payload['template'] = $this->template ? [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ] : null;
            $payload['current_version'] = $this->currentVersion
                ? WorkflowVersionResource::make($this->currentVersion)
                : null;
        }

        return $payload;
    }

    public function availableActions(User $actor, Workflow $workflow): array
    {
        $workflowAuthorizationService = app(WorkflowAuthorizationService::class);
        if (! $workflowAuthorizationService->canView($actor, $workflow)) {
            return [];
        }

        if ($workflow->status === WorkflowStatus::Deleted) {
            return $actor->role === Role::BusinessOwner ? ['view', 'purge'] : ['view'];
        }

        if ($workflowAuthorizationService->canManage($actor, $workflow)) {
            $statusAction = $workflow->status === WorkflowStatus::Active ? 'disable' : 'enable';

            return ['view', 'edit_draft', 'publish', $statusAction, 'delete'];
        }

        return ['view'];
    }
}
