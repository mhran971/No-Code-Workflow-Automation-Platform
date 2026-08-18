<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Models\WorkflowInstance;

class WorkflowExecutionsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Daily Workflow Executions (Last 7 Days)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => Carbon::today()->subDays($i));

        $labels = $days->map(fn (Carbon $date) => $date->format('M d'))->toArray();

        $completedData = $days->map(function (Carbon $date) {
            return WorkflowInstance::whereDate('created_at', $date)
                ->where('status', WorkflowInstanceStatus::Completed)
                ->count();
        })->toArray();

        $failedData = $days->map(function (Carbon $date) {
            return WorkflowInstance::whereDate('created_at', $date)
                ->where('status', WorkflowInstanceStatus::Failed)
                ->count();
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Successful Executions',
                    'data' => $completedData,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                ],
                [
                    'label' => 'Failed Executions',
                    'data' => $failedData,
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.15)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
