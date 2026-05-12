<?php

namespace Modules\Workflows\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Workflows\Models\Node;

class NodeDefinitionService
{
    public function listActiveNodes(): Collection
    {
        return Node::query()
            ->where('is_active', true)
            ->with('configFields')
            ->orderBy('id')
            ->get();
    }
}
