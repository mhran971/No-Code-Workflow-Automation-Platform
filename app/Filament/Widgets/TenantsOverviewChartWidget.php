<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Models\Tenant;

class TenantsOverviewChartWidget extends ChartWidget
{
    protected ?string $heading = 'Tenants by Business Type';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $tenantsByType = Tenant::selectRaw('business_type, COUNT(*) as count')
            ->groupBy('business_type')
            ->pluck('count', 'business_type')
            ->toArray();

        $labels = [];
        $data = [];
        $palette = ['#6366F1', '#3B82F6', '#10B981', '#F59E0B', '#EC4899', '#8B5CF6', '#14B8A6', '#F97316'];
        $colors = [];

        $i = 0;
        foreach ($tenantsByType as $type => $count) {
            $label = $type instanceof BusinessType ? BusinessType::labels()[$type->value] ?? $type->value : (BusinessType::labels()[$type] ?? $type);
            $labels[] = $label;
            $data[] = $count;
            $colors[] = $palette[$i % count($palette)];
            $i++;
        }

        if (empty($labels)) {
            $labels = ['No tenants'];
            $data = [0];
            $colors = ['#94A3B8'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tenants',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
