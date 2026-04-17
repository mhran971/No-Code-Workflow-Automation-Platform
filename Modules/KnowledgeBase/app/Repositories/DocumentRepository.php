<?php

namespace Modules\KnowledgeBase\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\KnowledgeBase\Models\Document;

class DocumentRepository
{
    public function create(array $data): Document
    {
        return Document::create($data);
    }

    public function update(Document $document, array $data): Document
    {
        $document->update($data);

        return $document->fresh();
    }

    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return Document::where('tenant_id', $tenantId)
            ->with(['documentType', 'tags'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findForTenant(int $tenantId, int $id): ?Document
    {
        return Document::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->with(['documentType', 'tags'])
            ->first();
    }
}
