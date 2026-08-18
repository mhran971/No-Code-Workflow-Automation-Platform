<?php

namespace Modules\KnowledgeBase\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the standalone Python RAG microservice that indexes KnowledgeBase
 * documents for AI search/chat. Laravel stays the source of truth for the
 * document itself (storage, access, metadata) — this service only keeps the
 * vector index in sync with it via a shared-secret `X-API-Key` header.
 */
class RagService
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
     * Ingest a new document: upload + extract + chunk + embed + index.
     * $documentId should be the KnowledgeBase document's own primary key so
     * both systems reference the same identifier.
     *
     * @return array{document_id: string, chunks_count: int, status: string}
     */
    public function indexDocument(
        string $fileContents,
        string $filename,
        string $documentId,
        string $title,
        string $category,
        string $tenantId,
    ): array {
        $response = $this->client($this->timeout)
            ->attach('file', $fileContents, $filename)
            ->post("{$this->baseUrl}/index-document", [
                'article_id' => $documentId,
                'title' => $title,
                'category' => $category,
                'tenant_id' => $tenantId,
                'status' => 'active',
            ]);

        return $this->jsonOrFail($response, 'indexing');
    }

    /**
     * Flip a document's active/inactive flag in the index without touching
     * its indexed content. Inactive documents stay indexed but are excluded
     * from RAG search/chat results.
     *
     * @return array<string, mixed>
     */
    public function updateDocumentStatus(string $articleId, bool $isActive, string $tenantId): array
    {
        $response = $this->client(10)
            ->patch("{$this->baseUrl}/toggle-status", [
                'article_id' => $articleId,
                'status' => $isActive ? 'active' : 'inactive',
                'tenant_id' => $tenantId,
            ]);

        return $this->jsonOrFail($response, 'status sync');
    }

    /**
     * Cascade-delete a document from the vector DB, metadata DB, and the
     * RAG service's own on-disk copy.
     */
    public function deleteDocument(string $articleId, string $tenantId): void
    {
        $response = $this->client(15)
            ->post("{$this->baseUrl}/delete-document", [
                'document_id' => $articleId,
                'tenant_id' => $tenantId,
            ]);

        $this->jsonOrFail($response, 'deletion');
    }

    protected function client(int $timeout): PendingRequest
    {
        return Http::timeout($timeout)->withHeaders([
            'X-API-Key' => $this->apiKey,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonOrFail(Response $response, string $action): array
    {
        if ($response->failed()) {
            $detail = $response->json('detail') ?? $response->body();
            throw new RuntimeException("RAG service {$action} failed ({$response->status()}): {$detail}");
        }

        return $response->json() ?? [];
    }
}
