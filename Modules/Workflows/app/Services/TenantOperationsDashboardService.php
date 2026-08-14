<?php

namespace Modules\Workflows\Services;

use Illuminate\Support\Carbon;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowTask;

/**
 * Computes KPI cards for the Business Owner Tenant Operations Dashboard.
 *
 * All queries are scoped to a single tenant. The five KPIs are:
 * 1. Active Workflow Instances (non-terminal statuses)
 * 2. Pending Tasks (open tasks)
 * 3. Average Workflow Completion Time (seconds, computed in PHP for DB portability)
 * 4. Failed Instances (last 24 hours)
 * 5. SLA Breaches (open tasks whose due_at passed within the last 24 hours)
 */
class TenantOperationsDashboardService
{
    /**
     * Return all five KPI values for the given tenant.
     *
     * @return array{
     *     active_workflow_instances: int,
     *     pending_tasks: int,
     *     average_completion_time_seconds: float|null,
     *     average_completion_time_human: string|null,
     *     failed_instances_24h: int,
     *     sla_breaches_24h: int,
     * }
     */
    public function getKpis(int $tenantId): array
    {
        $now = Carbon::now();

        $averageSeconds = $this->computeAverageCompletionTime($tenantId);

        return [
            'active_workflow_instances' => $this->countActiveInstances($tenantId),
            'pending_tasks' => $this->countPendingTasks($tenantId),
            'average_completion_time_seconds' => $averageSeconds,
            'average_completion_time_human' => $averageSeconds !== null
                ? $this->formatSeconds($averageSeconds)
                : null,
            'failed_instances_24h' => $this->countFailedInstances24h($tenantId, $now),
            'sla_breaches_24h' => $this->countSlaBreaches24h($tenantId, $now),
        ];
    }

    /**
     * Count workflow instances in non-terminal statuses (running, waiting, paused, pending).
     */
    public function countActiveInstances(int $tenantId): int
    {
        return WorkflowInstance::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                WorkflowInstanceStatus::Running,
                WorkflowInstanceStatus::Waiting,
                WorkflowInstanceStatus::Paused,
                WorkflowInstanceStatus::Pending,
            ])
            ->count();
    }

    /**
     * Count tasks with status = 'open' (including overdue ones — they are still open).
     */
    public function countPendingTasks(int $tenantId): int
    {
        return WorkflowTask::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->count();
    }

    /**
     * Compute the average completion time in seconds for completed instances.
     *
     * Calculated in PHP (not raw SQL) for database portability — the test suite
     * uses SQLite while production uses PostgreSQL. Returns null when there are
     * no completed instances with both started_at and finished_at set.
     */
    public function computeAverageCompletionTime(int $tenantId): ?float
    {
        $completedInstances = WorkflowInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkflowInstanceStatus::Completed)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->get(['started_at', 'finished_at']);

        if ($completedInstances->isEmpty()) {
            return null;
        }

        $totalSeconds = $completedInstances->sum(function (WorkflowInstance $instance) {
            return $instance->finished_at->diffInSeconds($instance->started_at);
        });

        return round($totalSeconds / $completedInstances->count(), 1);
    }

    /**
     * Count instances that transitioned to 'failed' in the last 24 hours.
     */
    public function countFailedInstances24h(int $tenantId, Carbon $now): int
    {
        return WorkflowInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkflowInstanceStatus::Failed)
            ->where('updated_at', '>=', $now->copy()->subDay())
            ->count();
    }

    /**
     * Count open tasks whose due_at has passed within the last 24 hours (SLA breaches).
     *
     * An SLA breach is an open task that was supposed to be completed by due_at
     * but wasn't, and that due_at fell within the last 24 hours.
     */
    public function countSlaBreaches24h(int $tenantId, Carbon $now): int
    {
        return WorkflowTask::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->where('due_at', '>=', $now->copy()->subDay())
            ->count();
    }

    /**
     * Format seconds into a human-readable string like "1h 4m 5s".
     */
    protected function formatSeconds(float $totalSeconds): string
    {
        $seconds = (int) $totalSeconds;
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        if ($secs > 0 || empty($parts)) {
            $parts[] = "{$secs}s";
        }

        return implode(' ', $parts);
    }
}
