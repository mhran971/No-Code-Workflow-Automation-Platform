<?php

namespace Modules\KnowledgeBase\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\KnowledgeBase\Models\Tag;

class TagRepository
{
    /**
     * Get all tags (for admin).
     */
    public function getAll(): Collection
    {
        return Tag::with('tenant')->orderBy('name')->get();
    }

    /**
     * Get all tags for a tenant.
     */
    public function getAllForTenant(int $tenantId): Collection
    {
        return Tag::where('tenant_id', $tenantId)->orderBy('name')->get();
    }

    /**
     * Create a new tag.
     */
    public function create(array $data): Tag
    {
        return Tag::create($data);
    }
}
