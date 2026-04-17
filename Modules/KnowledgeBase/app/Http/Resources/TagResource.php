<?php

namespace Modules\KnowledgeBase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\KnowledgeBase\Models\Tag;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tenant_id' => $this->tenant_id,
        ];
    }
}
