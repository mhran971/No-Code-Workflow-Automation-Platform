<?php

namespace Modules\Workflows\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->id,
                'type' => $this->type,
                'label' => $this->label,
                'category' => $this->category?->value,
                'icon' => $this->icon,
                'description' => $this->description,
                'color' => $this->color,
                'is_active' => (bool) $this->is_active,
                'config_fields' => NodeConfigResource::collection($this->whenLoaded('configFields')),
        ];
    }
}
