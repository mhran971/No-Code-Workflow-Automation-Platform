<?php

namespace Modules\Customers\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Customers\Enums\CustomerLinkingField;
use Modules\Customers\Models\Customer;

/**
 * Runtime lookup-or-create for the customer-context trigger feature. Called from
 * Modules\Workflows\Services\Execution\Concerns\ResolvesCustomerContext (used by
 * ManualTriggerExecutor / FormTriggerExecutor) once a trigger's context is built.
 */
class CustomerResolutionService
{
    public function __construct(
        protected CustomerSettingsService $settingsService,
    ) {}

    public function resolve(int $tenantId, string $rawValue): ?Customer
    {
        $linkingField = $this->settingsService->getLinkingField($tenantId);

        if ($linkingField === null) {
            Log::warning('customers.resolve.no_linking_field_configured', ['tenant_id' => $tenantId]);

            return null;
        }

        $column = $linkingField->value;
        $value = $this->normalize($linkingField, $rawValue);

        if ($value === '') {
            return null;
        }

        if ($existing = Customer::query()->where('tenant_id', $tenantId)->where($column, $value)->first()) {
            return $existing;
        }

        try {
            return Customer::query()->create(['tenant_id' => $tenantId, $column => $value]);
        } catch (QueryException $e) {
            // Unique-constraint race: another concurrent trigger created the same customer
            // between our lookup and our create. Re-select rather than fail the instance.
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            return Customer::query()->where('tenant_id', $tenantId)->where($column, $value)->first();
        }
    }

    /**
     * Email: lowercase + trim (standard, unambiguous). Phone: whitespace/dashes/parens
     * stripped, leading "+" preserved — deliberately not full E.164 normalization, which
     * would need a phone-parsing library and a per-tenant default-country concept that
     * don't exist anywhere in this codebase yet.
     */
    protected function normalize(CustomerLinkingField $linkingField, string $rawValue): string
    {
        if ($linkingField === CustomerLinkingField::Email) {
            return Str::lower(trim($rawValue));
        }

        $hasPlus = str_starts_with(trim($rawValue), '+');
        $digits = preg_replace('/[^0-9]/', '', $rawValue) ?? '';

        return $digits === '' ? '' : ($hasPlus ? '+' : '').$digits;
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        // SQLSTATE 23000 (integrity constraint violation) covers both Postgres' unique_violation
        // and SQLite's UNIQUE constraint failure — this codebase runs on both (see CLAUDE.md).
        return $e->getCode() === '23000';
    }
}
