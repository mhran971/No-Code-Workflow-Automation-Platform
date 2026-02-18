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
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['required'],
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
            'tags.required' => 'At least one tag is required.',
            'tags.min' => 'At least one tag is required.',
        ];
    }

    /**
     * Add custom validation: ensure file is actually PDF by MIME if needed.
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
        });
    }
}
