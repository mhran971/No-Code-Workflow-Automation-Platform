<?php

namespace Modules\Workflows\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'version_label' => $this->version_label,
            'release_note' => $this->release_note,
            'published_at' => $this->published_at,
            'published_by' => $this->publishedBy ? [
                'id' => $this->publishedBy->id,
                'name' => $this->publishedBy->name,
                'email' => $this->publishedBy->email,
            ] : null,
        ];
    }
}
