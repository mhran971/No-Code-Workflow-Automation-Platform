<?php

namespace Modules\KnowledgeBase\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\KnowledgeBase\Models\Document;
use Modules\KnowledgeBase\Services\RagService;
use RuntimeException;
use Throwable;

/**
 * Pushes a newly uploaded document to the RAG service for indexing. Runs on
 * the queue because /index-document is fully synchronous on the RAG side
 * (text extraction, OCR fallback, embedding) and can take several seconds
 * for a large or scanned PDF — this keeps the upload request fast.
 */
class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(protected int $documentId) {}

    public function handle(RagService $ragService): void
    {
        $document = Document::with('documentType')->find($this->documentId);

        if (! $document) {
            return;
        }

        $disk = config('filesystems.default');

        if (! Storage::disk($disk)->exists($document->file_path)) {
            throw new RuntimeException('File not found on disk at time of indexing.');
        }

        $result = $ragService->indexDocument(
            fileContents: Storage::disk($disk)->get($document->file_path),
            filename: Str::slug($document->title).'.pdf',
            documentId: (string) $document->id,
            title: $document->title,
            category: $document->documentType?->name ?? 'general',
            tenantId: (string) $document->tenant_id,
        );

        $document->update([
            'index_status' => 'indexed',
            'index_error' => null,
            'chunks_count' => $result['chunks_count'] ?? null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Document::whereKey($this->documentId)->update([
            'index_status' => 'failed',
            'index_error' => $exception->getMessage(),
        ]);
    }
}
