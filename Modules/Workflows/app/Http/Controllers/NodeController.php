<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Workflows\Models\Node;
use Modules\Workflows\Transformers\NodeResource;

class NodeController extends Controller
{
    public function index(): JsonResponse
    {
        $nodes = Node::query()
            ->where('is_active', true)
            ->with('configFields')
            ->orderBy('id')
            ->get();

        return NodeResource::collection($nodes)->response();
    }
}
