<?php

namespace Modules\Workflows\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the external RAG microservice's AI workflow-generation endpoint —
 * the same service Modules\KnowledgeBase\Services\RagService indexes documents
 * into, reused here via the shared `services.rag` config (X-API-Key auth).
 */
class AiWorkflowGeneratorService
{
    protected string $baseUrl;

    protected int $timeout;

    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.rag.url'), '/');
        $this->timeout = (int) config('services.rag.timeout', 60);
        $this->apiKey = config('services.rag.api_key');
    }

    /**
     * @param  array<string, mixed>  $payload  AIWorkflowRequest fields: prompt, workflow_name, team_id,
     *                                          goal, trigger_description, steps, conditions,
     *                                          additional_requirements, article_ids
     * @return array<string, mixed> AIWorkflowResponse: success, workflow, validation, ai, context_documents_used
     */
    public function generate(array $payload): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->post("{$this->baseUrl}/api/v1/ai/workflows/generate", $payload);

        if ($response->failed()) {
            $detail = $response->json('detail') ?? $response->body();

            throw new RuntimeException("AI workflow generation failed ({$response->status()}): {$detail}");
        }

        return $response->json() ?? [];
    }
}
