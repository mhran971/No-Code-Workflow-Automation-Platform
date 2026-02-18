<?php

namespace Modules\KnowledgeBase\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\KnowledgeBase\Models\Tag;

class TagRepository
{
    /**
     * Get all tags (for admin).
     */
    public function all(): Collection
    {
        return Tag::with('tenant')->orderBy('name')->get();
    }

    /**
     * Get all tags for a tenant.
     */
    public function allForTenant(int $tenantId): Collection
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

    /**
     * Find a tag by name for tenant (case-insensitive).
     */
    public function findByName(string $name, int $tenantId): ?Tag
    {
        $normalized = strtolower(trim($name));
        if ($normalized === '') {
            return null;
        }

        return Tag::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();
    }

    /**
     * First or create a tag by name for the tenant (case-insensitive duplicate prevention).
     */
    public function firstOrCreateByName(string $name, int $tenantId): Tag
    {
        $existing = $this->findByName($name, $tenantId);
        if ($existing) {
            return $existing;
        }

        return $this->create([
            'tenant_id' => $tenantId,
            'name' => trim($name),
        ]);
    }
}
