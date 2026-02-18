<?php

namespace Modules\KnowledgeBase\Services;

use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Modules\Auth\Enums\Role;
use Modules\KnowledgeBase\Models\Tag;
use Modules\KnowledgeBase\Repositories\TagRepository;

class TagService extends BaseService
{
    protected ?string $repositoryClass = TagRepository::class;

    public function __construct(
        protected TagRepository $tagRepository
    ) {}

    /**
     * Get tags visible to the current user: all if admin, otherwise only their tenant's tags.
     */
    public function getTagsForCurrentUser(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        if ($user->role === Role::Admin) {
            return $this->tagRepository->all();
        }

        $tenantId = $user->tenant_id;

        if (! $tenantId) {
            return collect();
        }

        return $this->tagRepository->allForTenant($tenantId);
    }

    /**
     * Create a tag for the current user's tenant (find existing case-insensitive or create).
     */
    public function createTagForCurrentUser(string $name): Tag
    {
        $tenantId = (int) auth()->user()->tenant_id;

        return $this->tagRepository->firstOrCreateByName($name, $tenantId);
    }

    /**
     * Resolve mixed tag names/ids to tag IDs for the given tenant. Creates tags by name if they don't exist.
     * Prevents duplicates (case-insensitive) by normalizing names before resolve.
     *
     * @param  array<int|string>  $tagNamesOrIds
     * @return array<int>
     */
    public function resolveTagIdsForTenant(int $tenantId, array $tagNamesOrIds): array
    {
        $ids = [];
        $namesSeen = [];

        foreach ($tagNamesOrIds as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $tag = \Modules\KnowledgeBase\Models\Tag::where('id', (int) $item)
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($tag && ! in_array($tag->id, $ids, true)) {
                    $ids[] = $tag->id;
                }

                continue;
            }

            $name = is_string($item) ? trim($item) : '';
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
            if (isset($namesSeen[$key])) {
                continue;
            }
            $namesSeen[$key] = true;

            $tag = $this->tagRepository->firstOrCreateByName($name, $tenantId);
            if (! in_array($tag->id, $ids, true)) {
                $ids[] = $tag->id;
            }
        }

        return array_values($ids);
    }
}
