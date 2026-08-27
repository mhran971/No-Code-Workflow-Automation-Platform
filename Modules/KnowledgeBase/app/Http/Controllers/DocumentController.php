<?php

namespace Modules\KnowledgeBase\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log as FacadesLog;
use Modules\KnowledgeBase\Http\Requests\StoreDocumentRequest;
use Modules\KnowledgeBase\Http\Requests\UpdateDocumentRequest;
use Modules\KnowledgeBase\Http\Resources\DocumentResource;
use Modules\KnowledgeBase\Services\DocumentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService
    ) {}

    /**
     * List documents for the current user's tenant.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->get('per_page', 15);
        $documents = $this->documentService->listForCurrentUser($perPage);

        return DocumentResource::collection($documents);
    }

    /**
     * Upload a new PDF document.
     * Tags are sent as a comma-separated string (e.g. cv,cv2,cv3) and converted to an array here.
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $title = $request->input('title');
        $documentTypeId = (int) $request->input('document_type_id');
        $tags = StoreDocumentRequest::parseTagsString($request->input('tags', ''));

        $document = $this->documentService->upload(
            $file->getRealPath(),
            $title,
            $documentTypeId,
            $tags
        );

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    /**
     * Show document metadata (and links for preview/download). Only same-tenant access.
     */
    public function show(int $id): DocumentResource|JsonResponse
    {
        $document = $this->documentService->getForCurrentUser($id);

        if (! $document) {
            return response()->json(['message' => 'Document not found or access denied.'], 404);
        }

        return new DocumentResource($document);
    }

    /**
     * Update document metadata. Only same-tenant users. Uploaded date is not editable.
     * Tags are sent as a comma-separated string (e.g. cv,cv2,cv3) and converted to an array here.
     */
    public function update(UpdateDocumentRequest $request, int $id): DocumentResource|JsonResponse
    {
        $document = $this->documentService->getForCurrentUser($id);

        if (! $document) {
            return response()->json(['message' => 'Document not found or access denied.'], 404);
        }

        $data = $request->only(['title', 'document_type_id']);
        $tags = $request->has('tags')
            ? UpdateDocumentRequest::parseTagsString($request->input('tags', ''))
            : $document->tags->pluck('name')->all();

        $document = $this->documentService->updateMetadata($document, $data, $tags);

        return new DocumentResource($document);
    }

    /**
     * Stream the PDF for in-browser viewing (Content-Disposition: inline). Only same-tenant access.
     *
     * Same bytes as download(); the only difference is the disposition header, which is what decides
     * whether a viewer renders the file or the browser saves it to disk.
     */
    public function preview(int $id): StreamedResponse|JsonResponse
    {
        $document = $this->documentService->getForCurrentUser($id);

        if (! $document) {
            return response()->json(['message' => 'Document not found or access denied.'], 404);
        }

        return $this->documentService->getFileResponse($document, true);
    }

    /**
     * Download the PDF file as an attachment. Only same-tenant access.
     */
    public function download(int $id): StreamedResponse|JsonResponse
    {
        $document = $this->documentService->getForCurrentUser($id);

        if (! $document) {
            return response()->json(['message' => 'Document not found or access denied.'], 404);
        }

        return $this->documentService->getFileResponse($document, false);
    }

    public function setActive(int $id, $is_active)
    {
        FacadesLog::debug('activity', [$is_active]);
        $document = $this->documentService->getForCurrentUser($id);

        if (! $document) {
            return response()->json(['message' => 'Document not found or access denied.'], 404);
        }

        $document = $this->documentService->setActiveStatus($document, $is_active);

        return new DocumentResource($document);
    }

    public function destroy(int $id): JsonResponse
    {
        $document = $this->documentService->getForCurrentUser($id);

        if (! $document) {
            return response()->json(['message' => 'Document not found or access denied.'], 404);
        }

        $this->documentService->delete($document);

        return response()->json(['message' => 'Document deleted successfully.']);

    }
}
