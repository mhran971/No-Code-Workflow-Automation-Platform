<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Workflows\Http\Requests\ExportReportRequest;
use Modules\Workflows\Http\Requests\TeamPerformanceReportRequest;
use Modules\Workflows\Http\Requests\WorkflowAnalyticsReportRequest;
use Modules\Workflows\Services\Reports\ReportExportService;
use Modules\Workflows\Services\Reports\TeamPerformanceReportService;
use Modules\Workflows\Services\Reports\WorkflowAnalyticsReportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagerReportsController extends Controller
{
    public function __construct(
        protected TeamPerformanceReportService $teamPerformanceService,
        protected WorkflowAnalyticsReportService $workflowAnalyticsService,
        protected ReportExportService $reportExportService,
    ) {}

    /**
     * Get Manager Team Performance Report dataset.
     */
    public function teamPerformance(TeamPerformanceReportRequest $request): JsonResponse
    {
        $data = $this->teamPerformanceService->getTeamPerformance($this->actor(), $request->validated());

        return response()->json($data);
    }

    /**
     * Export Manager Team Performance Report as CSV or PDF.
     */
    public function exportTeamPerformance(ExportReportRequest $request): Response|StreamedResponse
    {
        $filters = $request->validated();
        $format = $filters['format'] ?? 'csv';
        unset($filters['format']);

        $data = $this->teamPerformanceService->getTeamPerformance($this->actor(), $filters);

        if ($format === 'pdf') {
            return $this->reportExportService->exportTeamPerformancePdf($data);
        }

        return $this->reportExportService->exportTeamPerformanceCsv($data);
    }

    /**
     * Get Manager Workflow Analytics Report dataset.
     */
    public function workflowAnalytics(WorkflowAnalyticsReportRequest $request): JsonResponse
    {
        $data = $this->workflowAnalyticsService->getWorkflowAnalytics($this->actor(), $request->validated());

        return response()->json($data);
    }

    /**
     * Export Manager Workflow Analytics Report as CSV or PDF.
     */
    public function exportWorkflowAnalytics(ExportReportRequest $request): Response|StreamedResponse
    {
        $filters = $request->validated();
        $format = $filters['format'] ?? 'csv';
        unset($filters['format']);

        $data = $this->workflowAnalyticsService->getWorkflowAnalytics($this->actor(), $filters);

        if ($format === 'pdf') {
            return $this->reportExportService->exportWorkflowAnalyticsPdf($data);
        }

        return $this->reportExportService->exportWorkflowAnalyticsCsv($data);
    }

    protected function actor(): User
    {
        /** @var User $actor */
        $actor = auth('api')->user();

        return $actor;
    }
}
