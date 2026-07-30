<?php

namespace Modules\Workflows\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Workflows\Models\WorkflowTask;

/**
 * @mixin WorkflowTask
 */
class WorkflowTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->displayStatus(),
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => trim($this->assignee->first_name.' '.$this->assignee->last_name),
            ]),
            'instance_id' => $this->instance_id,
            'node_key' => $this->node_key,
        ];
    }
}
