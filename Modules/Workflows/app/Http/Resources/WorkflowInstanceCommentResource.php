<?php

namespace Modules\Workflows\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Workflows\Models\WorkflowInstanceComment;

/**
 * @mixin WorkflowInstanceComment
 */
class WorkflowInstanceCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => trim($this->author->first_name.' '.$this->author->last_name),
                'email' => $this->author->email,
            ]),
        ];
    }
}
