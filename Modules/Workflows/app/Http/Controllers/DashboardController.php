<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Workflows\Services\TenantOperationsDashboardService;
use Modules\Workflows\Services\WorkflowAuthorizationService;

/**
 * Business Owner dashboard endpoints — returns aggregated KPI data
 * scoped to the authenticated user's tenant.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected TenantOperationsDashboardService $dashboardService,
        protected WorkflowAuthorizationService $authorizationService,
    ) {}

    /**
     * Tenant Operations Dashboard: returns the five KPI cards.
     *
     * - Active Workflow Instances
     * - Pending Tasks (total)
     * - Average Workflow Completion Time
     * - Failed Instances (last 24 hours)
     * - SLA Breaches (last 24 hours)
     */
    public function operations(): JsonResponse
    {
        $user = auth('api')->user();
        $this->authorizationService->assertBusinessOwner($user);

        $kpis = $this->dashboardService->getKpis((int) $user->tenant_id);

        return response()->json(['data' => $kpis]);
    }
}
