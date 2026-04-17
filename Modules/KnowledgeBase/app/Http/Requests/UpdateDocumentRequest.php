<?php

namespace Modules\KnowledgeBase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'tags' => ['sometimes', 'required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'document_type_id.required' => 'Document type is required.',
            'document_type_id.exists' => 'The selected document type is invalid.',
            'tags.required' => 'At least one tag is required (comma-separated, e.g. cv,cv2,cv3).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tagsString = $this->input('tags');
            if (is_string($tagsString) && $tagsString !== '') {
                $tags = $this->parseTagsString($tagsString);
                if (count($tags) < 1) {
                    $validator->errors()->add('tags', 'At least one tag is required (comma-separated, e.g. cv,cv2,cv3).');
                }
            }
        });
    }

    /**
     * Parse comma-separated tags string into an array of non-empty trimmed tag names.
     */
    public static function parseTagsString(string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $tags))));
    }
}
