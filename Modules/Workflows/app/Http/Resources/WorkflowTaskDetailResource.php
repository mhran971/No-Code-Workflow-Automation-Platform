<?php

namespace Modules\Workflows\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Workflows\Models\WorkflowTask;

/**
 * @mixin WorkflowTask
 */
class WorkflowTaskDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'input_schema' => $this->input_schema,
            'response' => $this->response,
            'draft_response' => $this->draft_response,
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => trim($this->assignee->first_name.' '.$this->assignee->last_name),
                'email' => $this->assignee->email,
                'position' => $this->assignee->position,
            ]),
            'completed_by' => $this->whenLoaded('completedBy', fn () => [
                'id' => $this->completedBy->id,
                'name' => trim($this->completedBy->first_name.' '.$this->completedBy->last_name),
            ]),
            'instance_id' => $this->instance_id,
            'node_key' => $this->node_key,
        ];
    }
}
