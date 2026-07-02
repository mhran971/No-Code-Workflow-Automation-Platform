<?php

namespace Modules\Workflows\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Workflows\Models\WorkflowInstanceAttachment;

/**
 * @mixin WorkflowInstanceAttachment
 */
class WorkflowInstanceAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'file_path' => $this->file_path,
            'url' => Storage::disk('public')->url($this->file_path),
            'created_at' => $this->created_at?->toIso8601String(),
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => [
                'id' => $this->uploadedBy->id,
                'name' => trim($this->uploadedBy->first_name.' '.$this->uploadedBy->last_name),
                'email' => $this->uploadedBy->email,
            ]),
        ];
    }
}