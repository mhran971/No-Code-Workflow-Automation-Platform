<?php

namespace Modules\Customers\Services;

use Illuminate\Validation\ValidationException;
use Modules\Customers\Enums\CustomerLinkingField;
use Modules\Customers\Models\CustomerSettings;
use Modules\Workflows\Models\Workflow;

class CustomerSettingsService
{
    public function getForTenant(int $tenantId): CustomerSettings
    {
        return CustomerSettings::query()->firstOrCreate(['tenant_id' => $tenantId]);
    }

    public function getLinkingField(int $tenantId): ?CustomerLinkingField
    {
        return CustomerSettings::query()
            ->where('tenant_id', $tenantId)
            ->value('linking_field');
    }

    /**
     * Update the tenant's linking field. Refuses if any of the tenant's *published* workflows
     * currently have customer context enabled on their live trigger — since published
     * `workflow_versions.definition` is immutable, changing the linking field out from under
     * an already-published mapping would silently make it semantically wrong (e.g. an "email"
     * form field now feeding a phone lookup) with no re-verification hook to catch it.
     *
     * This is a deliberate, one-off reach from Customers into Workflows' `Workflow` model —
     * the dependency direction elsewhere in this feature is Workflows -> Customers; this method
     * is the one place it runs the other way. Precedent for this kind of undeclared/one-off
     * cross-module coupling already exists elsewhere in this codebase (e.g. Auth <-> Team).
     */
    public function updateForTenant(int $tenantId, CustomerLinkingField $linkingField): CustomerSettings
    {
        $blocking = $this->publishedWorkflowsUsingCustomerContext($tenantId);

        if ($blocking !== []) {
            throw ValidationException::withMessages([
                'linking_field' => 'Cannot change the customer linking field while these published workflows have customer context enabled: '
                    .implode(', ', $blocking).'. Republish them without customer context first, or after switching.',
            ]);
        }

        $settings = $this->getForTenant($tenantId);
        $settings->update(['linking_field' => $linkingField]);

        return $settings;
    }

    /** @return list<string> names of published workflows with customer context enabled on their live trigger */
    protected function publishedWorkflowsUsingCustomerContext(int $tenantId): array
    {
        $names = [];

        Workflow::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('current_version_id')
            ->with('currentVersion')
            ->each(function (Workflow $workflow) use (&$names): void {
                $enabled = data_get($workflow->currentVersion?->definition, 'trigger.config.customerContextEnabled', false);
                if ($enabled === true) {
                    $names[] = $workflow->name;
                }
            });

        return $names;
    }
}
