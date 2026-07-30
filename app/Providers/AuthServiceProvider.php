<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Auth\Enums\Role;
use Modules\Workflows\Models\WorkflowInstanceAttachment;
use Modules\Workflows\Models\WorkflowInstanceComment;
use Modules\Workflows\Policies\WorkflowInstanceAttachmentPolicy;
use Modules\Workflows\Policies\WorkflowInstanceCommentPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        WorkflowInstanceAttachment::class => WorkflowInstanceAttachmentPolicy::class,
        WorkflowInstanceComment::class => WorkflowInstanceCommentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, string $ability): ?bool {
            if (($user->role ?? null) === Role::BusinessOwner) {
                return true;
            }

            return null;
        });
    }
}
