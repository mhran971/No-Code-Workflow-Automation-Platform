<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $maintenanceTenants = Tenant::where('maintenance_mode', true)->count();

        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();

        $totalWorkflows = Workflow::count();
        $publishedWorkflows = Workflow::where('status', WorkflowStatus::Active)->count();

        $totalRuns = WorkflowInstance::count();
        $completedRuns = WorkflowInstance::where('status', WorkflowInstanceStatus::Completed)->count();
        $failedRuns = WorkflowInstance::where('status', WorkflowInstanceStatus::Failed)->count();
        $successRate = $totalRuns > 0 ? round(($completedRuns / $totalRuns) * 100, 1) : 100;

        return [
            Stat::make('Total Tenants', $totalTenants)
                ->description("{$activeTenants} active" . ($maintenanceTenants > 0 ? " · {$maintenanceTenants} maintenance" : ''))
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary')
                ->chart([$totalTenants > 0 ? $activeTenants : 0, $totalTenants]),

            Stat::make('Platform Users', $totalUsers)
                ->description("{$activeUsers} active accounts")
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->chart([$activeUsers, $totalUsers]),

            Stat::make('Workflows Created', $totalWorkflows)
                ->description("{$publishedWorkflows} published")
                ->descriptionIcon('heroicon-m-arrow-path-rounded-square')
                ->color('warning')
                ->chart([$publishedWorkflows, $totalWorkflows]),

            Stat::make('Workflow Executions', $totalRuns)
                ->description("Success rate: {$successRate}% · Failed: {$failedRuns}")
                ->descriptionIcon($successRate >= 90 ? 'heroicon-m-check-badge' : 'heroicon-m-exclamation-triangle')
                ->color($successRate >= 90 ? 'success' : 'danger')
                ->chart([$completedRuns, $failedRuns, $totalRuns]),
        ];
    }
}
