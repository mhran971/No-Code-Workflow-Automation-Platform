<?php

namespace Modules\Workflows\Services;

use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\User;
use Modules\Workflows\Models\WorkflowTemplate;

class WorkflowTemplateService extends BaseService
{
    public function listTemplates(User $actor): Collection
    {
        return WorkflowTemplate::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($actor): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', (int) $actor->tenant_id);
            })
            ->orderBy('name')
            ->get();
    }

    public function resolveTemplate(User $actor, int $templateId): WorkflowTemplate
    {
        $template = WorkflowTemplate::query()
            ->where('id', $templateId)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($actor): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', (int) $actor->tenant_id);
            })
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages([
                'template_id' => 'Selected workflow template was not found.',
            ]);
        }

        return $template;
    }
}
