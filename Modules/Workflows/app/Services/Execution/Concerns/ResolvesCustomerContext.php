<?php

namespace Modules\Workflows\Services\Execution\Concerns;

use Modules\Customers\Services\CustomerResolutionService;
use Modules\Workflows\Services\Execution\NodeExecutionContext;

/**
 * Shared by ManualTriggerExecutor/FormTriggerExecutor. Resolves (or creates) the tenant's
 * Customer record for a trigger's "customer context" mapping, once the executor has already
 * built the instance's context (the mapped field's value only exists after that point).
 *
 * Requires the using class to constructor-inject a CustomerResolutionService.
 */
trait ResolvesCustomerContext
{
    protected CustomerResolutionService $customerResolver;

    protected function applyCustomerContext(NodeExecutionContext $context): void
    {
        $config = $context->config();

        if (($config['customerContextEnabled'] ?? false) !== true) {
            return;
        }

        $fieldKey = trim((string) ($config['customerContextField'] ?? ''));
        if ($fieldKey === '') {
            // Verification should have blocked publishing without a mapping selected;
            // defensive no-op rather than failing the instance.
            return;
        }

        $value = data_get($context->instance()->context ?? [], $fieldKey);
        if ($value === null || $value === '') {
            // See plan notes: manual-trigger's "declared" guarantee doesn't guarantee
            // non-empty, so this branch is reachable even on a fully verified workflow.
            return;
        }

        $customer = $this->customerResolver->resolve((int) $context->instance()->tenant_id, (string) $value);

        if ($customer !== null) {
            $context->setCustomerId((int) $customer->id);
        }
    }
}
