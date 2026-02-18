<?php

namespace Modules\KnowledgeBase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->tenant_id;
    }

    public function rules(): array
    {
        $maxKb = (int) config('knowledgebase.documents.max_file_size', 10240);

        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:'.$maxKb,
            ],
            'title' => ['required', 'string', 'max:255'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'tags' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        $maxKb = (int) config('knowledgebase.documents.max_file_size', 10240);
        $maxMb = round($maxKb / 1024, 1);

        return [
            'file.required' => 'Please select a file to upload.',
            'file.mimes' => 'Only PDF files are accepted.',
            'file.max' => "The file must not be larger than {$maxMb} MB.",
            'title.required' => 'Title is required.',
            'document_type_id.required' => 'Document type is required.',
            'document_type_id.exists' => 'The selected document type is invalid.',
            'tags.required' => 'At least one tag is required (comma-separated, e.g. cv,cv2,cv3).',
        ];
    }

    /**
     * Add custom validation: ensure file is actually PDF by MIME if needed,
     * and that tags string contains at least one non-empty tag.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasFile('file')) {
                return;
            }
            $file = $this->file('file');
            $allowed = config('knowledgebase.documents.allowed_mimes', ['application/pdf']);
            $mime = $file->getMimeType();
            if (! in_array($mime, $allowed, true)) {
                $validator->errors()->add('file', 'Only PDF files are accepted.');
            }

            $tagsString = $this->input('tags');
            if (is_string($tagsString)) {
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
