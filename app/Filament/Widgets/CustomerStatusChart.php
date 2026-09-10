<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\CustomerStatus;
use Filament\Widgets\ChartWidget;

class CustomerStatusChart extends ChartWidget
{
    protected ?string $heading = 'توزيع العملاء حسب الحالة';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getData(): array
    {
        $statuses = CustomerStatus::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $data = [];
        $labels = [];
        $colors = [];

        $colorMap = [
            'gray' => '#94a3b8',
            'warning' => '#f59e0b',
            'info' => '#3b82f6',
            'primary' => '#6366f1',
            'success' => '#10b981',
            'danger' => '#ef4444',
        ];

        foreach ($statuses as $status) {
            $count = Customer::where('status_id', $status->id)->count();
            if ($count > 0) {
                $data[] = $count;
                $labels[] = $status->name;
                $colors[] = $colorMap[$status->color] ?? '#94a3b8';
            }
        }

        // Customers without a status
        $noStatus = Customer::whereNull('status_id')->count();
        if ($noStatus > 0) {
            $data[] = $noStatus;
            $labels[] = 'بدون حالة';
            $colors[] = '#e2e8f0';
        }

        return [
            'datasets' => [
                [
                    'label' => 'العملاء',
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

    protected static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
