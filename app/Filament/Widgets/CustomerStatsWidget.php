<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CustomerStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        $query = Customer::query();

        // Stats are automatically scoped by CustomerScope global scope.
        $total = (clone $query)->count();
        $notContacted = (clone $query)->whereDoesntHave('activities')->count();
        $followUpToday = (clone $query)->whereDate('next_follow_up_at', today())->count();
        $overdue = (clone $query)->whereDate('next_follow_up_at', '<', today())->count();

        return [
            Stat::make('إجمالي العملاء', $total)
                ->description('عدد العملاء الكلي')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('primary'),

            Stat::make('لم يتم التواصل', $notContacted)
                ->description(round($total > 0 ? ($notContacted / $total) * 100 : 0).'%')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),

            Stat::make('متابعة اليوم', $followUpToday)
                ->description('تحتاج متابعة اليوم')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('متأخرة', $overdue)
                ->description('تأخرت متابعتها')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }

    protected static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
