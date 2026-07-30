<?php

namespace Modules\KnowledgeBase\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\KnowledgeBase\Http\Resources\DocumentResource;
use Modules\KnowledgeBase\Services\DocumentService;

/**
 * Lightweight mobile endpoints for documents.
 *
 * Re-uses DocumentService and DocumentResource — no new logic,
 * just a clean mobile route prefix.
 */
class MobileDocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService
    ) {}

    /**
     * B-28: List team documents (paginated, same-tenant).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->get('per_page', 15);
        $documents = $this->documentService->listForCurrentUser($perPage);

        return DocumentResource::collection($documents);
    }

    /**
     * B-29: Show document detail (same-tenant access only).
     */
    public function show(int $document): DocumentResource|JsonResponse
    {
        $doc = $this->documentService->getForCurrentUser($document);

        if (! $doc) {
            return response()->json(['message' => 'Document not found or access denied.'], 404);
        }

        return new DocumentResource($doc);
    }
}
