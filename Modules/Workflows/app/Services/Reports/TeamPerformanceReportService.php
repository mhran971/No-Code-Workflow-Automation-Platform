<?php

namespace Modules\Workflows\Services\Reports;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Models\WorkflowTask;

class TeamPerformanceReportService
{
    /**
     * Resolve the target team for the report, strictly scoping by actor role and tenant.
     *
     * @throws AuthorizationException
     */
    public function resolveAuthorizedTeam(User $actor, ?int $requestedTeamId = null): Team
    {
        if ($actor->role === Role::BusinessOwner) {
            if ($requestedTeamId !== null) {
                $team = Team::query()
                    ->where('tenant_id', (int) $actor->tenant_id)
                    ->where('id', $requestedTeamId)
                    ->first();

                if (! $team) {
                    throw new AuthorizationException('The specified team does not exist in your organization.');
                }

                return $team;
            }

            // If no specific team requested by BusinessOwner, fetch first tenant team.
            $team = Team::query()
                ->where('tenant_id', (int) $actor->tenant_id)
                ->first();

            if (! $team) {
                throw new AuthorizationException('No teams found in your organization.');
            }

            return $team;
        }

        if ($actor->role === Role::Manager) {
            $team = Team::query()
                ->where('tenant_id', (int) $actor->tenant_id)
                ->where('manager_id', (int) $actor->id)
                ->first();

            if (! $team) {
                throw new AuthorizationException('You are not assigned as a manager to any team.');
            }

            if ($requestedTeamId !== null && (int) $requestedTeamId !== (int) $team->id) {
                throw new AuthorizationException('You can only access reports for your own managed team.');
            }

            return $team;
        }

        throw new AuthorizationException('You do not have permission to view team performance reports.');
    }

    /**
     * Generate the complete Team Performance Report dataset.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTeamPerformance(User $actor, array $filters = []): array
    {
        $team = $this->resolveAuthorizedTeam($actor, isset($filters['team_id']) ? (int) $filters['team_id'] : null);

        // Date range handling (default: last 30 days)
        $dateFrom = isset($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $dateTo = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();

        // Resolve team members
        $memberships = TeamMembership::query()
            ->with('user')
            ->where('tenant_id', (int) $team->tenant_id)
            ->where('team_id', (int) $team->id)
            ->where('status', 'active')
            ->get();

        $teamUsers = $memberships->map(fn (TeamMembership $m) => $m->user)->filter();

        // If manager is not explicitly in team_memberships pivot, include them as member if needed
        $manager = $team->manager;
        if ($manager && ! $teamUsers->contains('id', $manager->id)) {
            $teamUsers->push($manager);
        }

        $allMemberIds = $teamUsers->pluck('id')->all();

        // Handle specific member filter
        $targetUserIds = $allMemberIds;
        if (! empty($filters['member_id'])) {
            $filterMemberId = (int) $filters['member_id'];
            if (! in_array($filterMemberId, $allMemberIds, true)) {
                throw new AuthorizationException('The selected member does not belong to this team.');
            }
            $targetUserIds = [$filterMemberId];
            $teamUsers = $teamUsers->where('id', $filterMemberId);
        }

        if (empty($targetUserIds)) {
            return $this->emptyReportStructure($team, $dateFrom, $dateTo);
        }

        // Query tasks strictly scoped to tenant and team members
        $tasks = WorkflowTask::query()
            ->where('tenant_id', (int) $team->tenant_id)
            ->whereIn('assignee_id', $targetUserIds)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        // Aggregate Metrics
        $totalAssigned = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed');
        $totalCompleted = $completedTasks->count();
        $openTasks = $tasks->where('status', 'open');
        $totalOpen = $openTasks->count();

        // Overdue count: expired open tasks OR tasks completed past their due date
        $overdueTasks = $tasks->filter(function (WorkflowTask $task): bool {
            if ($task->due_at === null) {
                return false;
            }

            if ($task->status === 'open') {
                return $task->due_at->isPast();
            }

            if ($task->status === 'completed' && $task->completed_at !== null) {
                return $task->completed_at->greaterThan($task->due_at);
            }

            return false;
        });

        $totalOverdue = $overdueTasks->count();
        $completionRate = $totalAssigned > 0 ? round(($totalCompleted / $totalAssigned) * 100, 2) : 0.0;
        $overdueRate = $totalAssigned > 0 ? round(($totalOverdue / $totalAssigned) * 100, 2) : 0.0;

        // Calculate average turnaround hours for completed tasks
        $totalDurationHours = 0.0;
        $validCompletedCount = 0;
        foreach ($completedTasks as $task) {
            if ($task->completed_at && $task->created_at) {
                $hours = $task->created_at->diffInMinutes($task->completed_at) / 60.0;
                $totalDurationHours += $hours;
                $validCompletedCount++;
            }
        }
        $avgTurnaroundHours = $validCompletedCount > 0 ? round($totalDurationHours / $validCompletedCount, 2) : 0.0;

        // Per-Member Statistics
        $membersData = [];
        foreach ($teamUsers as $user) {
            $userTasks = $tasks->where('assignee_id', $user->id);
            $userAssigned = $userTasks->count();
            $userCompletedTasks = $userTasks->where('status', 'completed');
            $userCompleted = $userCompletedTasks->count();
            $userOpen = $userTasks->where('status', 'open')->count();

            $userOverdue = $userTasks->filter(function (WorkflowTask $task): bool {
                if ($task->due_at === null) {
                    return false;
                }
                if ($task->status === 'open') {
                    return $task->due_at->isPast();
                }
                if ($task->status === 'completed' && $task->completed_at !== null) {
                    return $task->completed_at->greaterThan($task->due_at);
                }

                return false;
            })->count();

            $userDurationHours = 0.0;
            $userValidCompleted = 0;
            foreach ($userCompletedTasks as $task) {
                if ($task->completed_at && $task->created_at) {
                    $userDurationHours += ($task->created_at->diffInMinutes($task->completed_at) / 60.0);
                    $userValidCompleted++;
                }
            }

            $userAvgHours = $userValidCompleted > 0 ? round($userDurationHours / $userValidCompleted, 2) : 0.0;
            $userCompletionRate = $userAssigned > 0 ? round(($userCompleted / $userAssigned) * 100, 2) : 0.0;
            $userOnTimeCompleted = $userCompleted - $userTasks->where('status', 'completed')->filter(function ($t) {
                return $t->due_at && $t->completed_at && $t->completed_at->greaterThan($t->due_at);
            })->count();
            $userOnTimeRate = $userCompleted > 0 ? round(($userOnTimeCompleted / $userCompleted) * 100, 2) : 100.0;

            $membersData[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'assigned' => $userAssigned,
                'completed' => $userCompleted,
                'open' => $userOpen,
                'overdue' => $userOverdue,
                'completion_rate' => $userCompletionRate,
                'on_time_rate' => $userOnTimeRate,
                'avg_turnaround_hours' => $userAvgHours,
            ];
        }

        // Charts Datasets Preparation
        $charts = [
            'status_distribution' => [
                ['status' => 'completed', 'label' => 'Completed', 'count' => $totalCompleted, 'color' => '#10B981'],
                ['status' => 'open', 'label' => 'In Progress / Open', 'count' => $totalOpen, 'color' => '#3B82F6'],
                ['status' => 'overdue', 'label' => 'Overdue', 'count' => $totalOverdue, 'color' => '#EF4444'],
            ],
            'completion_trend' => $this->buildCompletionTrend($tasks, $dateFrom, $dateTo),
            'member_ranking' => collect($membersData)
                ->sortByDesc('completion_rate')
                ->values()
                ->map(fn ($m) => [
                    'user_id' => $m['user_id'],
                    'name' => $m['name'],
                    'completed' => $m['completed'],
                    'completion_rate' => $m['completion_rate'],
                    'avg_hours' => $m['avg_turnaround_hours'],
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
                'total_members' => count($teamUsers),
                'total_tasks_assigned' => $totalAssigned,
                'total_tasks_completed' => $totalCompleted,
                'total_tasks_open' => $totalOpen,
                'total_tasks_overdue' => $totalOverdue,
                'completion_rate' => $completionRate,
                'overdue_rate' => $overdueRate,
                'avg_turnaround_hours' => $avgTurnaroundHours,
            ],
            'charts' => $charts,
            'members' => $membersData,
        ];
    }

    /**
     * Build daily time-series trend of task creation and completion.
     *
     * @param  Collection<int, WorkflowTask>  $tasks
     * @return array<int, array<string, mixed>>
     */
    protected function buildCompletionTrend(Collection $tasks, Carbon $from, Carbon $to): array
    {
        $period = CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay());
        $trend = [];

        foreach ($period as $date) {
            $dateStr = $date->toDateString();
            $assignedOnDate = $tasks->filter(fn ($t) => $t->created_at && $t->created_at->toDateString() === $dateStr)->count();
            $completedOnDate = $tasks->filter(fn ($t) => $t->completed_at && $t->completed_at->toDateString() === $dateStr)->count();
            $overdueOnDate = $tasks->filter(function ($t) use ($dateStr) {
                return $t->due_at && $t->due_at->toDateString() === $dateStr && (
                    ($t->status === 'open' && $t->due_at->isPast()) ||
                    ($t->status === 'completed' && $t->completed_at && $t->completed_at->greaterThan($t->due_at))
                );
            })->count();

            $trend[] = [
                'date' => $dateStr,
                'assigned' => $assignedOnDate,
                'completed' => $completedOnDate,
                'overdue' => $overdueOnDate,
            ];
        }

        return $trend;
    }

    /**
     * Fallback report structure when no members or data are found.
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
                'total_members' => 0,
                'total_tasks_assigned' => 0,
                'total_tasks_completed' => 0,
                'total_tasks_open' => 0,
                'total_tasks_overdue' => 0,
                'completion_rate' => 0.0,
                'overdue_rate' => 0.0,
                'avg_turnaround_hours' => 0.0,
            ],
            'charts' => [
                'status_distribution' => [],
                'completion_trend' => [],
                'member_ranking' => [],
            ],
            'members' => [],
        ];
    }
}
