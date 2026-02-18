<?php

namespace Modules\KnowledgeBase\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\KnowledgeBase\Http\Resources\DocumentTypeResource;
use Modules\KnowledgeBase\Models\DocumentType;

class DocumentTypeController extends Controller
{
    /**
     * List all document types.
     */
    public function index(): AnonymousResourceCollection
    {
        $types = DocumentType::orderBy('name')->get();

        return DocumentTypeResource::collection($types);
    }
}
