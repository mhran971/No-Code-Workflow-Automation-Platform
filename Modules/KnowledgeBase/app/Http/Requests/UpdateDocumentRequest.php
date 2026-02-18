<?php

namespace Modules\KnowledgeBase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenantId = auth()->user()?->tenant_id;
        if (! $tenantId) {
            return false;
        }
        $id = $this->route('id');
        if (! $id) {
            return false;
        }
        $document = \Modules\KnowledgeBase\Models\Document::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        return $document !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'document_type_id' => ['sometimes', 'required', 'integer', 'exists:document_types,id'],
            'tags' => ['sometimes', 'required', 'array', 'min:1'],
            'tags.*' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'document_type_id.required' => 'Document type is required.',
            'document_type_id.exists' => 'The selected document type is invalid.',
            'tags.required' => 'At least one tag is required.',
            'tags.min' => 'At least one tag is required.',
        ];
    }
}
