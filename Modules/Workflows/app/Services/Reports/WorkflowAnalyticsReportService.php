<?php

namespace Modules\Workflows\Services\Reports;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;

class WorkflowAnalyticsReportService
{
    public function __construct(
        protected TeamPerformanceReportService $teamPerformanceService,
    ) {}

    /**
     * Generate the complete Workflow Analytics Report dataset.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getWorkflowAnalytics(User $actor, array $filters = []): array
    {
        $team = $this->teamPerformanceService->resolveAuthorizedTeam(
            $actor,
            isset($filters['team_id']) ? (int) $filters['team_id'] : null
        );

        // Date range handling (default: last 30 days)
        $dateFrom = isset($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $dateTo = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();

        // Get team workflows
        $workflowsQuery = Workflow::query()
            ->where('tenant_id', (int) $team->tenant_id)
            ->where('team_id', (int) $team->id)
            ->whereNull('deleted_at');

        if (! empty($filters['workflow_id'])) {
            $filterWorkflowId = (int) $filters['workflow_id'];
            $specificWorkflow = (clone $workflowsQuery)->where('id', $filterWorkflowId)->first();

            if (! $specificWorkflow) {
                throw new AuthorizationException('The selected workflow does not belong to your managed team.');
            }

            $workflows = collect([$specificWorkflow]);
            $workflowIds = [$filterWorkflowId];
        } else {
            $workflows = $workflowsQuery->get();
            $workflowIds = $workflows->pluck('id')->all();
        }

        if (empty($workflowIds)) {
            return $this->emptyReportStructure($team, $dateFrom, $dateTo);
        }

        // Query instances
        $instancesQuery = WorkflowInstance::query()
            ->where('tenant_id', (int) $team->tenant_id)
            ->whereIn('workflow_id', $workflowIds)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if (! empty($filters['status'])) {
            $statusValue = is_string($filters['status']) ? $filters['status'] : $filters['status']->value;
            $instancesQuery->where('status', $statusValue);
        }

        $instances = $instancesQuery->get();
        $instanceIds = $instances->pluck('id')->all();

        // Global KPIs
        $totalExecutions = $instances->count();
        $completedInstances = $instances->filter(fn ($i) => ($i->status instanceof \BackedEnum ? $i->status->value : $i->status) === 'completed');
        $failedInstances = $instances->filter(fn ($i) => ($i->status instanceof \BackedEnum ? $i->status->value : $i->status) === 'failed');
        $runningInstances = $instances->filter(fn ($i) => in_array($i->status instanceof \BackedEnum ? $i->status->value : $i->status, [
            'running', 'waiting', 'pending', 'paused',
        ], true));

        $successfulCount = $completedInstances->count();
        $failedCount = $failedInstances->count();
        $runningCount = $runningInstances->count();

        $successRate = $totalExecutions > 0 ? round(($successfulCount / $totalExecutions) * 100, 2) : 0.0;
        $failureRate = $totalExecutions > 0 ? round(($failedCount / $totalExecutions) * 100, 2) : 0.0;

        // Average duration of completed instances
        $totalDurationSec = 0.0;
        $validDurationCount = 0;
        foreach ($completedInstances as $instance) {
            if ($instance->started_at && $instance->finished_at) {
                $sec = $instance->started_at->diffInSeconds($instance->finished_at);
                $totalDurationSec += $sec;
                $validDurationCount++;
            }
        }
        $avgDurationSeconds = $validDurationCount > 0 ? round($totalDurationSec / $validDurationCount, 2) : 0.0;

        // Bottlenecks & Node Execution Analysis
        $bottlenecks = $this->analyzeBottlenecks((int) $team->tenant_id, $instanceIds, $workflows);

        // Per-Workflow Breakdown Table
        $workflowsTable = [];
        foreach ($workflows as $wf) {
            $wfInstances = $instances->where('workflow_id', $wf->id);
            $wfTotal = $wfInstances->count();
            $wfSuccess = $wfInstances->filter(fn ($i) => ($i->status instanceof \BackedEnum ? $i->status->value : $i->status) === 'completed')->count();
            $wfFailed = $wfInstances->filter(fn ($i) => ($i->status instanceof \BackedEnum ? $i->status->value : $i->status) === 'failed')->count();

            $wfTotalSec = 0.0;
            $wfValidCount = 0;
            foreach ($wfInstances as $inst) {
                $instStatus = $inst->status instanceof \BackedEnum ? $inst->status->value : $inst->status;
                if ($instStatus === 'completed' && $inst->started_at && $inst->finished_at) {
                    $wfTotalSec += $inst->started_at->diffInSeconds($inst->finished_at);
                    $wfValidCount++;
                }
            }
            $wfAvgSec = $wfValidCount > 0 ? round($wfTotalSec / $wfValidCount, 2) : 0.0;
            $wfSuccessRate = $wfTotal > 0 ? round(($wfSuccess / $wfTotal) * 100, 2) : 0.0;

            $lastRun = $wfInstances->sortByDesc('created_at')->first();

            // Find slowest node for this specific workflow
            $wfBottleneck = collect($bottlenecks)->firstWhere('workflow_id', $wf->id);

            $workflowsTable[] = [
                'id' => $wf->id,
                'name' => $wf->name,
                'status' => $wf->status instanceof \BackedEnum ? $wf->status->value : (string) ($wf->status ?? ''),
                'version' => $wf->current_version_number ?? 1,
                'total_runs' => $wfTotal,
                'success_runs' => $wfSuccess,
                'failed_runs' => $wfFailed,
                'success_rate' => $wfSuccessRate,
                'avg_duration_seconds' => $wfAvgSec,
                'avg_duration_formatted' => $this->formatDuration($wfAvgSec),
                'last_run_at' => $lastRun?->created_at?->toIso8601String(),
                'bottleneck_node' => $wfBottleneck ? [
                    'node_key' => $wfBottleneck['node_key'],
                    'node_type' => $wfBottleneck['node_type'],
                    'avg_duration_seconds' => $wfBottleneck['avg_duration_seconds'],
                ] : null,
            ];
        }

        // Charts Datasets Preparation
        $charts = [
            'status_breakdown' => $this->buildStatusBreakdown($instances),
            'execution_trend' => $this->buildExecutionTrend($instances, $dateFrom, $dateTo),
            'top_failing_workflows' => collect($workflowsTable)
                ->where('failed_runs', '>', 0)
                ->sortByDesc('failed_runs')
                ->values()
                ->take(5)
                ->map(fn ($w) => [
                    'workflow_id' => $w['id'],
                    'name' => $w['name'],
                    'failed_runs' => $w['failed_runs'],
                    'total_runs' => $w['total_runs'],
                ])
                ->all(),
        ];

        return [
            'status' => 'success',
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'manager' => $team->manager ? [
                    'id' => $team->manager->id,
                    'name' => $team->manager->name,
                    'email' => $team->manager->email,
                ] : null,
            ],
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'summary' => [
                'total_workflows' => $workflows->count(),
                'total_executions' => $totalExecutions,
                'successful_executions' => $successfulCount,
                'failed_executions' => $failedCount,
                'running_executions' => $runningCount,
                'success_rate' => $successRate,
                'failure_rate' => $failureRate,
                'avg_duration_seconds' => $avgDurationSeconds,
                'avg_duration_formatted' => $this->formatDuration($avgDurationSeconds),
            ],
            'charts' => $charts,
            'bottlenecks' => $bottlenecks,
            'workflows' => $workflowsTable,
        ];
    }

    /**
     * Identify and rank bottleneck nodes based on execution durations and failure rates.
     *
     * @param  array<int>  $instanceIds
     * @param  Collection<int, Workflow>  $workflows
     * @return array<int, array<string, mixed>>
     */
    protected function analyzeBottlenecks(int $tenantId, array $instanceIds, Collection $workflows): array
    {
        if (empty($instanceIds)) {
            return [];
        }

        $nodeExecutions = WorkflowNodeExecution::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('instance_id', $instanceIds)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->get();

        if ($nodeExecutions->isEmpty()) {
            return [];
        }

        $grouped = $nodeExecutions->groupBy(fn (WorkflowNodeExecution $ne) => $ne->instance_id . ':' . $ne->node_key);

        // Aggregate by node_key across instances
        $nodeAggregates = [];
        $instanceWorkflowMap = WorkflowInstance::whereIn('id', $instanceIds)->pluck('workflow_id', 'id')->all();
        $workflowNameMap = $workflows->pluck('name', 'id')->all();

        foreach ($nodeExecutions->groupBy('node_key') as $nodeKey => $executions) {
            $first = $executions->first();
            $nodeType = $first->node_type;
            $sampleInstanceId = $first->instance_id;
            $workflowId = $instanceWorkflowMap[$sampleInstanceId] ?? null;
            $workflowName = $workflowId ? ($workflowNameMap[$workflowId] ?? 'Unknown') : 'Unknown';

            $durations = $executions->map(function (WorkflowNodeExecution $ne) {
                return $ne->started_at && $ne->finished_at
                    ? $ne->started_at->diffInMilliseconds($ne->finished_at) / 1000.0
                    : 0.0;
            });

            $avgDuration = $durations->count() > 0 ? round($durations->avg(), 2) : 0.0;
            $maxDuration = $durations->count() > 0 ? round($durations->max(), 2) : 0.0;
            $failureCount = $executions->filter(fn ($ne) => ($ne->status instanceof \BackedEnum ? $ne->status->value : $ne->status) === 'failed')->count();

            $nodeAggregates[] = [
                'workflow_id' => $workflowId,
                'workflow_name' => $workflowName,
                'node_key' => $nodeKey,
                'node_type' => $nodeType,
                'execution_count' => $executions->count(),
                'avg_duration_seconds' => $avgDuration,
                'max_duration_seconds' => $maxDuration,
                'failure_count' => $failureCount,
            ];
        }

        return collect($nodeAggregates)
            ->sortByDesc('avg_duration_seconds')
            ->values()
            ->take(10)
            ->all();
    }

    /**
     * Build status breakdown dataset for chart display.
     *
     * @param  Collection<int, WorkflowInstance>  $instances
     * @return array<int, array<string, mixed>>
     */
    protected function buildStatusBreakdown(Collection $instances): array
    {
        $statusColors = [
            'completed' => '#10B981',
            'failed' => '#EF4444',
            'running' => '#3B82F6',
            'waiting' => '#F59E0B',
            'paused' => '#8B5CF6',
            'cancelled' => '#6B7280',
            'pending' => '#9CA3AF',
        ];

        $counts = [];
        foreach ($instances as $inst) {
            $st = $inst->status instanceof \BackedEnum ? $inst->status->value : (string) ($inst->status ?? '');
            $counts[$st] = ($counts[$st] ?? 0) + 1;
        }

        $result = [];
        foreach ($statusColors as $status => $color) {
            $count = $counts[$status] ?? 0;
            if ($count > 0 || $instances->isEmpty()) {
                $result[] = [
                    'status' => $status,
                    'label' => ucfirst($status),
                    'count' => $count,
                    'color' => $color,
                ];
            }
        }

        return $result;
    }

    /**
     * Build execution time-series trend (daily volume, successes, failures).
     *
     * @param  Collection<int, WorkflowInstance>  $instances
     * @return array<int, array<string, mixed>>
     */
    protected function buildExecutionTrend(Collection $instances, Carbon $from, Carbon $to): array
    {
        $period = CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay());
        $trend = [];

        foreach ($period as $date) {
            $dateStr = $date->toDateString();
            $dayInstances = $instances->filter(fn ($i) => $i->created_at && $i->created_at->toDateString() === $dateStr);
            $total = $dayInstances->count();
            $success = $dayInstances->filter(fn ($i) => ($i->status instanceof \BackedEnum ? $i->status->value : $i->status) === 'completed')->count();
            $failed = $dayInstances->filter(fn ($i) => ($i->status instanceof \BackedEnum ? $i->status->value : $i->status) === 'failed')->count();

            $trend[] = [
                'date' => $dateStr,
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
            ];
        }

        return $trend;
    }

    /**
     * Format duration into human readable string (e.g., 2m 15s).
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds - ($minutes * 60));

        return "{$minutes}m {$remainingSeconds}s";
    }

    /**
     * Fallback report structure when no workflows or data are found.
     *
     * @return array<string, mixed>
     */
    protected function emptyReportStructure(Team $team, Carbon $dateFrom, Carbon $dateTo): array
    {
        return [
            'status' => 'success',
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'manager' => $team->manager ? [
                    'id' => $team->manager->id,
                    'name' => $team->manager->name,
                    'email' => $team->manager->email,
                ] : null,
            ],
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'summary' => [
                'total_workflows' => 0,
                'total_executions' => 0,
                'successful_executions' => 0,
                'failed_executions' => 0,
                'running_executions' => 0,
                'success_rate' => 0.0,
                'failure_rate' => 0.0,
                'avg_duration_seconds' => 0.0,
                'avg_duration_formatted' => '0s',
            ],
            'charts' => [
                'status_breakdown' => [],
                'execution_trend' => [],
                'top_failing_workflows' => [],
            ],
            'bottlenecks' => [],
            'workflows' => [],
        ];
    }
}
