<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\Role;
use Modules\KnowledgeBase\Models\Document;

class GenerateWorkflowWithAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth('api')->user()?->role, [Role::Manager, Role::BusinessOwner], true);
    }

    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string'],
            'goal' => ['nullable', 'string'],
            'workflow_name' => ['nullable', 'string', 'max:255'],
            'team_id' => ['nullable', 'integer'],
            'trigger_description' => ['nullable', 'string'],
            'steps' => ['nullable', 'array'],
            'steps.*' => ['string'],
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['string'],
            'additional_requirements' => ['nullable', 'string'],
            'article_ids' => ['nullable', 'array'],
            'article_ids.*' => ['integer'],
        ];
    }

    /**
     * Mirrors the RAG service's own `check_prompt_or_goal` cross-field rule so a bad
     * request is rejected here rather than round-tripping to the external service first.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $prompt = trim((string) $this->input('prompt'));
            $goal = trim((string) $this->input('goal'));

            if (mb_strlen($prompt) < 5 && mb_strlen($goal) < 5) {
                $validator->errors()->add(
                    'prompt',
                    "Either 'prompt' or 'goal' must be provided with at least 5 characters."
                );
            }

            if ($this->filled('article_ids')) {
                $tenantId = (int) auth('api')->user()?->tenant_id;
                $ids = array_unique(array_map('intval', (array) $this->input('article_ids')));

                $found = Document::query()
                    ->whereIn('id', $ids)
                    ->where('tenant_id', $tenantId)
                    ->count();

                if ($found !== count($ids)) {
                    $validator->errors()->add('article_ids', 'One or more selected knowledge base documents were not found.');
                }
            }
        });
    }
}
