<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Workflows\Models\Node;
use Modules\Workflows\Models\NodeConfigField;
use Modules\Workflows\Services\NodeDefinitionService;

class NodeController extends Controller
{
    public function __construct(
        protected NodeDefinitionService $nodeDefinitionService
    ) {}

    public function index(): JsonResponse
    {
        $nodes = $this->nodeDefinitionService->listActiveNodes();

        return response()->json([
            'data' => $nodes->map(fn (Node $node) => [
                'id' => $node->id,
                'type' => $node->type,
                'label' => $node->label,
                'category' => $node->category?->value,
                'icon' => $node->icon,
                'description' => $node->description,
                'color' => $node->color,
                'is_active' => (bool) $node->is_active,
                'config_fields' => $node->configFields->map(fn (NodeConfigField $field) => [
                    'id' => $field->id,
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type?->value,
                    'placeholder' => $field->placeholder,
                    'options' => $field->options,
                    'required' => (bool) $field->is_required,
                    'default_value' => $field->default_value,
                ])->values(),
            ])->values(),
        ]);
    }
}
