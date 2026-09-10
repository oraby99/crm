<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class SalesPerformanceChart extends ChartWidget
{
    protected ?string $heading = 'أداء مندوبي المبيعات (العملاء المتواصل معهم مقابل غير المتواصل معهم)';
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return Auth::user()?->isAdmin() || Auth::user()?->isTeamLeader();
    }

    protected function getData(): array
    {
        $user = Auth::user();

        // Get sales reps (either all for admin, or team leader's reps)
        $query = User::where('role', UserRole::Sales->value)->where('is_active', true);
        
        if ($user && $user->isTeamLeader()) {
            $query->where('team_leader_id', $user->id);
        }

        $salesReps = $query->withCount([
            'assignedCustomers as contacted_count' => function ($query) {
                $query->whereHas('activities');
            },
            'assignedCustomers as uncontacted_count' => function ($query) {
                $query->whereDoesntHave('activities');
            }
        ])->get();

        return [
            'datasets' => [
                [
                    'label' => 'تم التواصل',
                    'data' => $salesReps->pluck('contacted_count')->toArray(),
                    'backgroundColor' => '#10b981', // green
                ],
                [
                    'label' => 'لم يتم التواصل',
                    'data' => $salesReps->pluck('uncontacted_count')->toArray(),
                    'backgroundColor' => '#ef4444', // red
                ],
            ],
            'labels' => $salesReps->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
