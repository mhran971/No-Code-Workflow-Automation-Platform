<?php

namespace Modules\KnowledgeBase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\KnowledgeBase\Models\Document;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
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
            'title' => $this->title,
            'document_type' => new DocumentTypeResource($this->whenLoaded('documentType')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'uploaded_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
