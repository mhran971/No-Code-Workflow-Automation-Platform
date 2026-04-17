<?php

namespace Modules\KnowledgeBase\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\KnowledgeBase\Http\Resources\TagResource;
use Modules\KnowledgeBase\Services\TagService;

class TagController extends Controller
{
    public function __construct(
        protected TagService $tagService
    ) {}

    /**
     * List tags for the current user's tenant, or all tags if user is admin.
     */
    public function index(): AnonymousResourceCollection
    {
        $tags = $this->tagService->getTagsForCurrentUser();

        return TagResource::collection($tags);
    }

    /**
     * Create a new tag for the current user's tenant (or return existing if name matches case-insensitive).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tag = $this->tagService->createTagForCurrentUser($validated['name']);

        return (new TagResource($tag))->response()->setStatusCode(201);
    }
}
