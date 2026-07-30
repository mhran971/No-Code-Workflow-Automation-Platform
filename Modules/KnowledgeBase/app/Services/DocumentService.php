<?php

namespace Modules\KnowledgeBase\Services;

use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Log;
use Modules\KnowledgeBase\Models\Document;
use Modules\KnowledgeBase\Repositories\DocumentRepository;
use Modules\KnowledgeBase\Repositories\TagRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService extends BaseService
{
    protected ?string $repositoryClass = DocumentRepository::class;

    public function __construct(
        protected DocumentRepository $documentRepository,
        protected TagRepository $tagRepository,
        protected TagService $tagService
    ) {}

    /**
     * Store uploaded file and create document under the current user's tenant.
     *
     * @param  array<int|string>  $tagNamesOrIds  Tag names (strings) or tag IDs (ints). Duplicates resolved case-insensitive.
     */
    public function upload(string $path, string $title, int $documentTypeId, array $tagNamesOrIds): Document
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $tagIds = $this->tagService->resolveTagIdsForTenant($tenantId, $tagNamesOrIds);

        $storedPath = $this->storeFile($path, $tenantId);

        $document = $this->documentRepository->create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'document_type_id' => $documentTypeId,
            'file_path' => $storedPath,
        ]);

        $document->tags()->sync($tagIds);

        return $document->load(['documentType', 'tags']);
    }

    /**
     * Update document metadata (title, document type, tags). Uploaded date is not changed.
     *
     * @param  array<int|string>  $tagNamesOrIds
     */
    public function updateMetadata(Document $document, array $data, array $tagNamesOrIds): Document
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $tagIds = $this->tagService->resolveTagIdsForTenant($tenantId, $tagNamesOrIds);

        $document->update([
            'title' => $data['title'] ?? $document->title,
            'document_type_id' => $data['document_type_id'] ?? $document->document_type_id,
        ]);

        $document->tags()->sync($tagIds);

        $document->refresh();
        $document->load(['documentType', 'tags']);

        return $document;
    }

    public function listForCurrentUser(int $perPage = 15): LengthAwarePaginator
    {
        $tenantId = (int) auth()->user()->tenant_id;

        return $this->documentRepository->paginateForTenant($tenantId, $perPage);
    }

    public function getForCurrentUser(int $id): ?Document
    {
        $tenantId = (int) auth()->user()->tenant_id;

        return $this->documentRepository->findForTenant($tenantId, $id);
    }

    /**
     * Return a stream response for the document file (download or inline).
     */
    public function getFileResponse(Document $document, bool $inline = false): StreamedResponse
    {
        $path = $document->file_path;
        $disk = config('filesystems.default');
        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'File not found.');
        }

        $mime = 'application/pdf';
        $filename = Str::slug($document->title).'.pdf';

        return Storage::disk($disk)->response(
            $path,
            $filename,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => $inline
                    ? 'inline; filename="'.$filename.'"'
                    : 'attachment; filename="'.$filename.'"',
            ]
        );
    }

    public function setActiveStatus(Document $document, bool $isActive): Document
    {
        // TODO: check if there are active workflows using this document before deactivating
        $document->update(['is_active' => (bool) $isActive]);
        Log::debug('Document active status updated', ['is_active' => $isActive, 'document' => $document]);

        return $document;
    }

    public function delete(Document $document): bool
    {
        // TODO: Check if there is historical data before deletion. If there is, we can only set is_active to false and keep the file for audit purposes.
        $document->delete();

        return true;
    }

    private function storeFile(string $tmpPath, int $tenantId): string
    {
        $disk = config('filesystems.default');
        $dir = "documents/{$tenantId}";
        $name = Str::uuid().'.pdf';

        Storage::disk($disk)->putFileAs($dir, new File($tmpPath), $name);

        return "{$dir}/{$name}";
    }
}
