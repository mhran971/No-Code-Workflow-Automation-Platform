<?php

namespace Modules\KnowledgeBase\Services;

use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Modules\Auth\Enums\Role;
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
            return $this->tagRepository->getAll();
        }

        $tenantId = $user->tenant_id;

        if (! $tenantId) {
            return collect();
        }

        return $this->tagRepository->getAllForTenant($tenantId);
    }
}
