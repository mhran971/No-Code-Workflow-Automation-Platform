<?php

namespace Modules\Workflows\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowVersionResource extends JsonResource
{
    /** @var bool */
    protected $includeDefinition = false;

    /**
     * Create a resource instance that includes the definition field.
     */
    public static function withDefinition($resource): static
    {
        $instance = new static($resource);
        $instance->includeDefinition = true;

        return $instance;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'version_label' => $this->version_label,
            'release_note' => $this->release_note,
            'is_current' => $this->relationLoaded('workflow') && $this->workflow
                ? (int) $this->id === (int) $this->workflow->current_version_id
                : null,
            'rollback_source_version_id' => $this->rollback_source_version_id,
            'published_at' => $this->published_at,
            'published_by' => $this->publishedBy ? [
                'id' => $this->publishedBy->id,
                'name' => $this->publishedBy->name,
                'email' => $this->publishedBy->email,
            ] : null,
        ];

        if ($this->includeDefinition) {
            $payload['definition'] = $this->definition;
        }

        return $payload;
    }
}
