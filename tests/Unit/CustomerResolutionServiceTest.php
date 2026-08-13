<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Models\Tenant;
use Modules\Customers\Enums\CustomerLinkingField;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerSettings;
use Modules\Customers\Services\CustomerResolutionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerResolutionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CustomerResolutionService::class);
    }

    protected function tenantWithLinkingField(CustomerLinkingField $field): Tenant
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme Inc', 'business_type' => BusinessType::SaaS->value]);
        CustomerSettings::query()->create(['tenant_id' => $tenant->id, 'linking_field' => $field->value]);

        return $tenant;
    }

    #[Test]
    public function it_returns_null_when_tenant_has_no_linking_field_configured(): void
    {
        $tenant = Tenant::query()->create(['business_name' => 'Acme Inc', 'business_type' => BusinessType::SaaS->value]);

        $result = $this->service->resolve((int) $tenant->id, 'someone@example.test');

        $this->assertNull($result);
        $this->assertDatabaseCount('customers', 0);
    }

    #[Test]
    public function it_returns_null_when_value_is_empty(): void
    {
        $tenant = $this->tenantWithLinkingField(CustomerLinkingField::Email);

        $this->assertNull($this->service->resolve((int) $tenant->id, '   '));
        $this->assertDatabaseCount('customers', 0);
    }

    #[Test]
    public function it_creates_a_customer_with_normalized_email(): void
    {
        $tenant = $this->tenantWithLinkingField(CustomerLinkingField::Email);

        $customer = $this->service->resolve((int) $tenant->id, '  Jane.Doe@Example.TEST  ');

        $this->assertNotNull($customer);
        $this->assertSame('jane.doe@example.test', $customer->email);
        $this->assertSame($tenant->id, $customer->tenant_id);
    }

    #[Test]
    public function it_reuses_an_existing_customer_by_normalized_email(): void
    {
        $tenant = $this->tenantWithLinkingField(CustomerLinkingField::Email);
        $existing = Customer::query()->create(['tenant_id' => $tenant->id, 'email' => 'jane@example.test']);

        $resolved = $this->service->resolve((int) $tenant->id, 'JANE@EXAMPLE.TEST');

        $this->assertSame($existing->id, $resolved->id);
        $this->assertDatabaseCount('customers', 1);
    }

    #[Test]
    public function it_normalizes_phone_numbers_by_stripping_formatting(): void
    {
        $tenant = $this->tenantWithLinkingField(CustomerLinkingField::Phone);

        $customer = $this->service->resolve((int) $tenant->id, '+1 (555) 123-4567');

        $this->assertSame('+15551234567', $customer->phone);
    }

    #[Test]
    public function it_scopes_lookup_by_tenant(): void
    {
        $tenantA = $this->tenantWithLinkingField(CustomerLinkingField::Email);
        $tenantB = $this->tenantWithLinkingField(CustomerLinkingField::Email);
        Customer::query()->create(['tenant_id' => $tenantA->id, 'email' => 'shared@example.test']);

        $resolvedForB = $this->service->resolve((int) $tenantB->id, 'shared@example.test');

        $this->assertNotNull($resolvedForB);
        $this->assertSame($tenantB->id, $resolvedForB->tenant_id);
        $this->assertDatabaseCount('customers', 2);
    }
}
