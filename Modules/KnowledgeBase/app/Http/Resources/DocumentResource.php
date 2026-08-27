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
            'is_active' => $this->is_active,
            'index_status' => $this->index_status,
            'chunks_count' => $this->chunks_count,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            // Both routes sit behind auth:api, so a client must fetch them with its bearer token and
            // render the response body (e.g. via a blob URL) — an <iframe src> carries no Authorization
            // header and will come back 401.
            'preview_url' => route('api.documents.preview', ['id' => $this->id]),
            'download_url' => route('api.documents.download', ['id' => $this->id]),
        ];
    }
}
