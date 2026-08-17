<?php

namespace Modules\Workflows\Services\Reports;

use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;

class WorkflowRecommendationsReportService
{
    public function __construct(
        protected TeamPerformanceReportService $teamPerformanceService,
    ) {}

    /**
     * Generate the complete Workflow Recommendations Report dataset.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getRecommendations(User $actor, array $filters = []): array
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

        $failureThreshold = isset($filters['failure_threshold']) ? (float) $filters['failure_threshold'] : 10.0;
        $cancellationThreshold = isset($filters['cancellation_threshold']) ? (float) $filters['cancellation_threshold'] : 15.0;
        $minExecutions = isset($filters['min_executions']) ? (int) $filters['min_executions'] : 3;

        // Fetch team workflows
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

        // Fetch instances in scope
        $instances = WorkflowInstance::query()
            ->where('tenant_id', (int) $team->tenant_id)
            ->whereIn('workflow_id', $workflowIds)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $instanceIds = $instances->pluck('id')->all();

        // 1. Detect Bottlenecks (>50% above workflow median)
        $bottlenecks = $this->detectBottlenecks((int) $team->tenant_id, $instanceIds, $workflows, $minExecutions);

        // 2. Detect High Failure Rate Nodes (>= failureThreshold, default 10%)
        $highFailureNodes = $this->detectHighFailureNodes((int) $team->tenant_id, $instanceIds, $workflows, $failureThreshold, $minExecutions);

        // 3. Detect High Abandonment/Cancellation Workflows (>= cancellationThreshold, default 15%)
        $highCancellationWorkflows = $this->detectHighCancellationWorkflows($workflows, $instances, $cancellationThreshold, $minExecutions);

        // 4. Detect SLA Breach Hot Spots
        $slaBreachHotSpots = $this->detectSlaBreachHotSpots((int) $team->tenant_id, $instanceIds, $workflows);

        // Calculate overall Health Score
        $healthScore = $this->calculateHealthScore(
            $bottlenecks,
            $highFailureNodes,
            $highCancellationWorkflows,
            $slaBreachHotSpots
        );

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
            'thresholds' => [
                'failure_rate_threshold_percent' => $failureThreshold,
                'cancellation_rate_threshold_percent' => $cancellationThreshold,
                'bottleneck_median_excess_percent' => 50.0,
                'min_executions' => $minExecutions,
            ],
            'health_score' => $healthScore,
            'recommendations' => [
                'bottlenecks' => $bottlenecks,
                'high_failure_nodes' => $highFailureNodes,
                'high_cancellation_workflows' => $highCancellationWorkflows,
                'sla_breach_hot_spots' => $slaBreachHotSpots,
            ],
        ];
    }

    /**
     * Detect bottleneck nodes where average wait/execution duration exceeds workflow median by > 50%.
     *
     * @param  array<int>  $instanceIds
     * @param  Collection<int, Workflow>  $workflows
     * @return array<int, array<string, mixed>>
     */
    public function detectBottlenecks(int $tenantId, array $instanceIds, Collection $workflows, int $minExecutions = 3): array
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

        $instanceWorkflowMap = WorkflowInstance::whereIn('id', $instanceIds)->pluck('workflow_id', 'id')->all();
        $workflowNameMap = $workflows->pluck('name', 'id')->all();

        // Group node executions by workflow_id
        $workflowNodeGroups = [];
        foreach ($nodeExecutions as $ne) {
            $wfId = $instanceWorkflowMap[$ne->instance_id] ?? null;
            if (! $wfId) {
                continue;
            }
            $workflowNodeGroups[$wfId][$ne->node_key][] = $ne;
        }

        $bottlenecks = [];

        foreach ($workflowNodeGroups as $wfId => $nodes) {
            $nodeAvgDurations = [];
            $nodeMeta = [];

            foreach ($nodes as $nodeKey => $executions) {
                $count = count($executions);
                $totalSeconds = 0.0;
                foreach ($executions as $ne) {
                    $totalSeconds += $ne->started_at->diffInMilliseconds($ne->finished_at) / 1000.0;
                }
                $avgSec = $count > 0 ? round($totalSeconds / $count, 2) : 0.0;
                $nodeAvgDurations[$nodeKey] = $avgSec;

                $first = $executions[0];
                $nodeMeta[$nodeKey] = [
                    'node_type' => $first->node_type,
                    'execution_count' => $count,
                ];
            }

            if (empty($nodeAvgDurations)) {
                continue;
            }

            // Calculate median of node averages for this workflow
            $durations = array_values($nodeAvgDurations);
            sort($durations);
            $numNodes = count($durations);
            $middleIndex = (int) floor($numNodes / 2);

            if ($numNodes % 2 === 0) {
                $medianSec = ($durations[$middleIndex - 1] + $durations[$middleIndex]) / 2.0;
            } else {
                $medianSec = $durations[$middleIndex];
            }

            $medianSec = round($medianSec, 2);

            // Baseline threshold: 50% above median
            $thresholdSec = $medianSec * 1.5;

            foreach ($nodeAvgDurations as $nodeKey => $avgSec) {
                $meta = $nodeMeta[$nodeKey];
                if ($meta['execution_count'] >= $minExecutions && $avgSec > $thresholdSec && $medianSec > 0.05) {
                    $excessPercent = round((($avgSec - $medianSec) / $medianSec) * 100, 1);
                    $severity = $excessPercent >= 150.0 ? 'critical' : 'warning';

                    $suggestion = match ($meta['node_type']) {
                        'send-email', 'clickup-create-task', 'hubspot-create-contact', 'hubspot-create-deal' =>
                            "External integration duration is {$excessPercent}% above workflow median ({$avgSec}s vs {$medianSec}s). Review external API timeouts or run in background branch.",
                        'ai-generator' =>
                            "AI generation duration is {$excessPercent}% above median. Optimize prompt length or leverage document caching.",
                        'task-node' =>
                            "Human task completion time is {$excessPercent}% higher than other steps. Consider adjusting SLA windows or adding automated reminders.",
                        default =>
                            "Node execution duration is {$excessPercent}% above workflow median ({$avgSec}s vs {$medianSec}s). Optimize internal logic or consider parallel execution.",
                    };

                    $bottlenecks[] = [
                        'workflow_id' => $wfId,
                        'workflow_name' => $workflowNameMap[$wfId] ?? 'Unknown',
                        'node_key' => $nodeKey,
                        'node_type' => $meta['node_type'],
                        'node_label' => ucfirst(str_replace(['-', '_'], ' ', $meta['node_type'])),
                        'execution_count' => $meta['execution_count'],
                        'avg_duration_seconds' => $avgSec,
                        'workflow_median_seconds' => $medianSec,
                        'excess_percentage' => $excessPercent,
                        'severity' => $severity,
                        'suggestion' => $suggestion,
                    ];
                }
            }
        }

        // Sort by excess percentage descending
        usort($bottlenecks, fn ($a, $b) => $b['excess_percentage'] <=> $a['excess_percentage']);

        return $bottlenecks;
    }

    /**
     * Detect nodes exceeding the configurable failure rate threshold (default: 10%).
     *
     * @param  array<int>  $instanceIds
     * @param  Collection<int, Workflow>  $workflows
     * @return array<int, array<string, mixed>>
     */
    public function detectHighFailureNodes(
        int $tenantId,
        array $instanceIds,
        Collection $workflows,
        float $failureThreshold = 10.0,
        int $minExecutions = 3
    ): array {
        if (empty($instanceIds)) {
            return [];
        }

        $nodeExecutions = WorkflowNodeExecution::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('instance_id', $instanceIds)
            ->get();

        if ($nodeExecutions->isEmpty()) {
            return [];
        }

        $instanceWorkflowMap = WorkflowInstance::whereIn('id', $instanceIds)->pluck('workflow_id', 'id')->all();
        $workflowNameMap = $workflows->pluck('name', 'id')->all();

        $groupedByWorkflowAndNode = [];
        foreach ($nodeExecutions as $ne) {
            $wfId = $instanceWorkflowMap[$ne->instance_id] ?? null;
            if (! $wfId) {
                continue;
            }
            $groupedByWorkflowAndNode[$wfId][$ne->node_key][] = $ne;
        }

        $failingNodes = [];

        foreach ($groupedByWorkflowAndNode as $wfId => $nodes) {
            foreach ($nodes as $nodeKey => $executions) {
                $total = count($executions);
                if ($total < $minExecutions) {
                    continue;
                }

                $failedCount = 0;
                $errors = [];

                foreach ($executions as $ne) {
                    $status = $ne->status instanceof \BackedEnum ? $ne->status->value : $ne->status;
                    if ($status === 'failed') {
                        $failedCount++;
                        if (! empty($ne->error)) {
                            $errStr = is_array($ne->error)
                                ? ($ne->error['message'] ?? json_encode($ne->error))
                                : (string) $ne->error;
                            if (! in_array($errStr, $errors, true)) {
                                $errors[] = $errStr;
                            }
                        }
                    }
                }

                $failureRate = round(($failedCount / $total) * 100, 1);

                if ($failureRate >= $failureThreshold && $failedCount > 0) {
                    $first = $executions[0];
                    $nodeType = $first->node_type;
                    $severity = $failureRate >= 30.0 ? 'critical' : 'warning';

                    $suggestion = match ($nodeType) {
                        'send-email' =>
                            "Email delivery failure rate is {$failureRate}%. Check SMTP/Gmail OAuth connectivity and recipient email validation.",
                        'clickup-create-task', 'hubspot-create-contact', 'hubspot-create-deal' =>
                            "Integration node failure rate is {$failureRate}%. Verify third-party API credentials, required payload fields, and rate limits.",
                        'ai-generator' =>
                            "AI generation failure rate is {$failureRate}%. Validate model prompt context size and API token quotas.",
                        default =>
                            "Node failure rate is {$failureRate}%. Inspect error payloads and ensure required input variables are available in upstream nodes.",
                    };

                    $failingNodes[] = [
                        'workflow_id' => $wfId,
                        'workflow_name' => $workflowNameMap[$wfId] ?? 'Unknown',
                        'node_key' => $nodeKey,
                        'node_type' => $nodeType,
                        'node_label' => ucfirst(str_replace(['-', '_'], ' ', $nodeType)),
                        'total_executions' => $total,
                        'failed_executions' => $failedCount,
                        'failure_rate' => $failureRate,
                        'severity' => $severity,
                        'common_errors' => array_slice($errors, 0, 3),
                        'suggestion' => $suggestion,
                    ];
                }
            }
        }

        // Sort by failure rate descending
        usort($failingNodes, fn ($a, $b) => $b['failure_rate'] <=> $a['failure_rate']);

        return $failingNodes;
    }

    /**
     * Detect workflows with high abandonment or cancellation rates (default: 15%).
     *
     * @param  Collection<int, Workflow>  $workflows
     * @param  Collection<int, WorkflowInstance>  $instances
     * @return array<int, array<string, mixed>>
     */
    public function detectHighCancellationWorkflows(
        Collection $workflows,
        Collection $instances,
        float $cancellationThreshold = 15.0,
        int $minExecutions = 3
    ): array {
        $cancellationReports = [];

        foreach ($workflows as $wf) {
            $wfInstances = $instances->where('workflow_id', $wf->id);
            $total = $wfInstances->count();

            if ($total < $minExecutions) {
                continue;
            }

            $cancelled = $wfInstances->filter(function ($inst) {
                $status = $inst->status instanceof \BackedEnum ? $inst->status->value : $inst->status;

                return $status === 'cancelled';
            });

            $cancelledCount = $cancelled->count();
            $cancellationRate = round(($cancelledCount / $total) * 100, 1);

            if ($cancellationRate >= $cancellationThreshold && $cancelledCount > 0) {
                $severity = $cancellationRate >= 30.0 ? 'critical' : 'warning';

                // Calculate average runtime before cancellation
                $totalCancelRuntimeSec = 0.0;
                $validCancelCount = 0;
                foreach ($cancelled as $cInst) {
                    if ($cInst->started_at && $cInst->finished_at) {
                        $totalCancelRuntimeSec += $cInst->started_at->diffInSeconds($cInst->finished_at);
                        $validCancelCount++;
                    }
                }
                $avgCancelRuntime = $validCancelCount > 0 ? round($totalCancelRuntimeSec / $validCancelCount, 1) : 0.0;

                $cancellationReports[] = [
                    'workflow_id' => $wf->id,
                    'workflow_name' => $wf->name,
                    'total_instances' => $total,
                    'cancelled_instances' => $cancelledCount,
                    'cancellation_rate' => $cancellationRate,
                    'avg_runtime_before_cancel_seconds' => $avgCancelRuntime,
                    'severity' => $severity,
                    'suggestion' => "Workflow has a {$cancellationRate}% cancellation rate ({$cancelledCount} of {$total} runs cancelled). Review long-running pause/approval stages and streamline prerequisites to minimize drop-offs.",
                ];
            }
        }

        // Sort by cancellation rate descending
        usort($cancellationReports, fn ($a, $b) => $b['cancellation_rate'] <=> $a['cancellation_rate']);

        return $cancellationReports;
    }

    /**
     * Detect SLA breach hot spots in human tasks and timer-based nodes.
     *
     * @param  array<int>  $instanceIds
     * @param  Collection<int, Workflow>  $workflows
     * @return array<int, array<string, mixed>>
     */
    public function detectSlaBreachHotSpots(
        int $tenantId,
        array $instanceIds,
        Collection $workflows
    ): array {
        if (empty($instanceIds)) {
            return [];
        }

        $tasks = WorkflowTask::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('instance_id', $instanceIds)
            ->whereNotNull('due_at')
            ->with(['assignee:id,first_name,last_name,name,email'])
            ->get();

        if ($tasks->isEmpty()) {
            return [];
        }

        $instanceWorkflowMap = WorkflowInstance::whereIn('id', $instanceIds)->pluck('workflow_id', 'id')->all();
        $workflowNameMap = $workflows->pluck('name', 'id')->all();

        $grouped = [];
        $now = Carbon::now();

        foreach ($tasks as $task) {
            $wfId = $instanceWorkflowMap[$task->instance_id] ?? null;
            if (! $wfId) {
                continue;
            }
            $grouped[$wfId][$task->node_key][] = $task;
        }

        $hotSpots = [];

        foreach ($grouped as $wfId => $nodeTasks) {
            foreach ($nodeTasks as $nodeKey => $items) {
                $totalTasks = count($items);
                $breachedTasks = 0;
                $totalOverdueHours = 0.0;
                $assigneeBreaches = [];
                $sampleTaskTitle = $items[0]->title ?? 'Task Step';

                foreach ($items as $task) {
                    $isBreached = false;
                    $overdueHours = 0.0;

                    if ($task->status === 'completed' && $task->completed_at && $task->due_at && $task->completed_at->gt($task->due_at)) {
                        $isBreached = true;
                        $overdueHours = $task->due_at->diffInHours($task->completed_at, true);
                    } elseif ($task->status === 'expired') {
                        $isBreached = true;
                        $overdueHours = $task->due_at ? $task->due_at->diffInHours($task->updated_at ?? $now, true) : 0.0;
                    } elseif ($task->status === 'open' && $task->due_at && $now->gt($task->due_at)) {
                        $isBreached = true;
                        $overdueHours = $task->due_at->diffInHours($now, true);
                    }

                    if ($isBreached) {
                        $breachedTasks++;
                        $totalOverdueHours += $overdueHours;

                        if ($task->assignee) {
                            $userId = $task->assignee->id;
                            if (! isset($assigneeBreaches[$userId])) {
                                $assigneeBreaches[$userId] = [
                                    'user_id' => $userId,
                                    'name' => $task->assignee->name ?? trim($task->assignee->first_name . ' ' . $task->assignee->last_name),
                                    'breach_count' => 0,
                                ];
                            }
                            $assigneeBreaches[$userId]['breach_count']++;
                        }
                    }
                }

                if ($breachedTasks > 0) {
                    $breachRate = round(($breachedTasks / $totalTasks) * 100, 1);
                    $avgOverdueHours = round($totalOverdueHours / $breachedTasks, 1);
                    $severity = $breachRate >= 30.0 ? 'critical' : 'warning';

                    usort($assigneeBreaches, fn ($a, $b) => $b['breach_count'] <=> $a['breach_count']);

                    $hotSpots[] = [
                        'workflow_id' => $wfId,
                        'workflow_name' => $workflowNameMap[$wfId] ?? 'Unknown',
                        'node_key' => $nodeKey,
                        'task_title' => $sampleTaskTitle,
                        'total_tasks' => $totalTasks,
                        'breached_tasks' => $breachedTasks,
                        'breach_rate' => $breachRate,
                        'avg_overdue_hours' => $avgOverdueHours,
                        'severity' => $severity,
                        'top_delayed_assignees' => array_slice(array_values($assigneeBreaches), 0, 3),
                        'suggestion' => "{$breachRate}% of human tasks at this node breached SLA with an average delay of {$avgOverdueHours} hours. Consider expanding the SLA deadline or assigning automated escalation triggers.",
                    ];
                }
            }
        }

        // Sort by breach rate descending
        usort($hotSpots, fn ($a, $b) => $b['breach_rate'] <=> $a['breach_rate']);

        return $hotSpots;
    }

    /**
     * Calculate overall workflow health score from 0 to 100.
     *
     * @param  array<int, mixed>  $bottlenecks
     * @param  array<int, mixed>  $highFailureNodes
     * @param  array<int, mixed>  $highCancellationWorkflows
     * @param  array<int, mixed>  $slaBreachHotSpots
     * @return array<string, mixed>
     */
    protected function calculateHealthScore(
        array $bottlenecks,
        array $highFailureNodes,
        array $highCancellationWorkflows,
        array $slaBreachHotSpots
    ): array {
        $score = 100;
        $criticalCount = 0;
        $warningCount = 0;

        $allItems = array_merge($bottlenecks, $highFailureNodes, $highCancellationWorkflows, $slaBreachHotSpots);

        foreach ($allItems as $item) {
            $severity = $item['severity'] ?? 'warning';
            if ($severity === 'critical') {
                $criticalCount++;
                $score -= 10;
            } else {
                $warningCount++;
                $score -= 4;
            }
        }

        $score = max(0, min(100, $score));

        $grade = match (true) {
            $score >= 90 => 'Optimal',
            $score >= 75 => 'Good',
            $score >= 50 => 'Needs Attention',
            default => 'Critical',
        };

        return [
            'score' => $score,
            'grade' => $grade,
            'total_recommendations' => count($allItems),
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
        ];
    }

    /**
     * Return an empty recommendations report structure when no workflows or data are found.
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
            'thresholds' => [
                'failure_rate_threshold_percent' => 10.0,
                'cancellation_rate_threshold_percent' => 15.0,
                'bottleneck_median_excess_percent' => 50.0,
                'min_executions' => 3,
            ],
            'health_score' => [
                'score' => 100,
                'grade' => 'Optimal',
                'total_recommendations' => 0,
                'critical_count' => 0,
                'warning_count' => 0,
            ],
            'recommendations' => [
                'bottlenecks' => [],
                'high_failure_nodes' => [],
                'high_cancellation_workflows' => [],
                'sla_breach_hot_spots' => [],
            ],
        ];
    }
}
