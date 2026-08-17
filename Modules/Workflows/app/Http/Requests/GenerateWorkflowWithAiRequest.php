<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\Role;

class GenerateWorkflowWithAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth('api')->user()?->role, [Role::Manager, Role::BusinessOwner], true);
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string'],
            // Plain workflow metadata — same field/limit as StoreWorkflowRequest's `description`
            // (method=blank/template). Never sent to the AI; just carried through the preview.
            'description' => ['nullable', 'string', 'max:2000'],
            'workflow_name' => ['nullable', 'string', 'max:255'],
            'team_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Mirrors RAG's own rule: `prompt` required, ≥5 characters after trimming whitespace.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->has('prompt') && mb_strlen(trim((string) $this->input('prompt'))) < 5) {
                $validator->errors()->add('prompt', 'The prompt must be at least 5 characters.');
            }
        });
    }
}
