<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomerStatus;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ReportStatsWidget extends BaseWidget
{
    public ?string $period = 'this_month';

    public ?string $dateFrom = null;

    public ?string $dateUntil = null;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return false;
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        $fromDate = Carbon::parse($this->dateFrom ?? now()->startOfMonth())->startOfDay();
        $untilDate = Carbon::parse($this->dateUntil ?? now()->endOfMonth())->endOfDay();

        // Base customer query for period
        $customerQuery = Customer::query()->whereBetween('created_at', [$fromDate, $untilDate]);
        if ($user && $user->isTeamLeader()) {
            $customerQuery->where('team_leader_id', $user->id);
        }

        $totalNewCustomers = (clone $customerQuery)->count();

        // Base activity query for period
        $activityQuery = CustomerActivity::query()->whereBetween('created_at', [$fromDate, $untilDate]);
        if ($user && $user->isTeamLeader()) {
            $activityQuery->whereHas('customer', fn ($q) => $q->where('team_leader_id', $user->id));
        }

        $totalActivities = (clone $activityQuery)->count();

        // Won / Converted customers count
        $wonStatusIds = CustomerStatus::where('is_final', true)->pluck('id');
        $wonCustomersCount = (clone $customerQuery)
            ->whereIn('status_id', $wonStatusIds)
            ->count();

        $overallConversionRate = $totalNewCustomers > 0
            ? round(($wonCustomersCount / $totalNewCustomers) * 100, 1)
            : 0;

        // Average response time (hours between customer creation and 1st activity)
        $customersWithActivity = (clone $customerQuery)
            ->whereHas('activities')
            ->with(['activities' => fn ($q) => $q->oldest()])
            ->get();

        $totalResponseHours = 0;
        $responseCount = 0;

        foreach ($customersWithActivity as $c) {
            $firstAct = $c->activities->first();
            if ($firstAct && $c->created_at) {
                $hours = $c->created_at->diffInHours($firstAct->created_at);
                $totalResponseHours += $hours;
                $responseCount++;
            }
        }

        $avgResponseHours = $responseCount > 0 ? round($totalResponseHours / $responseCount, 1) : 0;

        return [
            Stat::make('إجمالي العملاء الجدد', number_format($totalNewCustomers))
                ->description('عميل جديد في الفترة')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('إجمالي الأنشطة والمكالمات', number_format($totalActivities))
                ->description('تواصل ومتابعة منفذة')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('success'),

            Stat::make('المبيعات المغلقة (التحويـل)', number_format($wonCustomersCount))
                ->description('صفقة ناجحة تمت')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('warning'),

            Stat::make('معدل التحويل الإجمالي', "{$overallConversionRate}%")
                ->description('نسبة نجاح الصفقات')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('primary'),

            Stat::make('متوسط أول استجابة', "{$avgResponseHours} ساعة")
                ->description('زمن أول تواصل مع العميل')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
