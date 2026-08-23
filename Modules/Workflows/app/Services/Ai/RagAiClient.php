<?php

namespace Modules\Workflows\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Workflows\Services\Execution\Contracts\AiContentGenerator;
use Modules\Workflows\Services\Execution\Contracts\AiTextClassifier;
use Modules\Workflows\Services\Execution\Exceptions\AiServiceException;

/**
 * Talks to the external RAG microservice's text-generation and classification endpoints
 * (`POST /api/v1/ai/generate`, `POST /api/v1/ai/classify`) — see docs/RAG_AI_NODES_API.md.
 * Reuses the shared `services.rag` config (X-API-Key auth) also used by
 * Modules\KnowledgeBase\Services\RagService and AiWorkflowGeneratorService.
 *
 * Both endpoints return HTTP 200 even on a "soft failure" (bad LLM output / unable to classify),
 * signaled only via `success: false` in the body — a plain `$response->failed()` check is not enough.
 */
class RagAiClient implements AiContentGenerator, AiTextClassifier
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

    public function generate(string $prompt, string $tenantId, array $config = []): string
    {
        $tone = trim((string) ($config['tone'] ?? ''));
        $articleIds = $this->articleIds($config);

        $payload = array_filter([
            'prompt' => $prompt,
            'tenant_id' => $tenantId,
            'tone' => $tone !== '' ? $tone : null,
        ], fn (mixed $value): bool => $value !== null);

        $body = $this->post('/api/v1/ai/generate', $payload);

        if (($body['success'] ?? false) !== true) {
            throw new AiServiceException(
                (string) ($body['error'] ?? 'AI content generation failed.'),
                200,
                retryable: true,
            );
        }

        return (string) ($body['content'] ?? '');
    }

    /**
     * @param  list<string>  $categories
     * @return array{classification: string, confidence: float}
     */
    public function classify(string $text, array $categories, string $tenantId): array
    {
        $body = $this->post('/api/v1/ai/classify', [
            'text' => $text,
            'categories' => array_values($categories),
            'tenant_id' => $tenantId,
        ]);

        if (($body['success'] ?? false) !== true) {
            throw new AiServiceException(
                (string) ($body['error'] ?? 'AI classification failed.'),
                200,
                retryable: true,
            );
        }

        return [
            'classification' => (string) ($body['classification'] ?? ''),
            'confidence' => (float) ($body['confidence'] ?? 0.0),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function post(string $path, array $payload): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->post("{$this->baseUrl}{$path}", $payload);

        if ($response->failed()) {
            throw new AiServiceException(
                $this->errorDetail($response),
                $response->status(),
                retryable: $response->status() >= 500 || $response->status() === 429,
            );
        }

        return $response->json() ?? [];
    }

    protected function errorDetail(Response $response): string
    {
        return (string) ($response->json('detail') ?? $response->body());
    }

    /**
     * The `ai-generator` node's `knowledgeBaseDocuments` config field holds KnowledgeBase document IDs
     * (the same IDs RagService indexes documents under) — forward them as RAG's `article_ids`.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function articleIds(array $config): array
    {
        $raw = $config['knowledgeBaseDocuments'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $id): string => (string) $id,
            array_filter($raw, static fn (mixed $id): bool => is_scalar($id) && (string) $id !== ''),
        ));
    }
}
