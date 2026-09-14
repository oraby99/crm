<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomerNeed;
use App\Models\CustomerStatus;
use App\Models\Platform;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ReportsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'التقارير والتحليلات';

    protected static ?string $title = 'تقارير أداء المبيعات والتقرير الشهري';

    protected static string|\UnitEnum|null $navigationGroup = 'التقارير والإحصائيات';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.reports-page';

    public string $period = 'this_month';

    public ?string $dateFrom = null;

    public ?string $dateUntil = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->isTeamLeader());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('طباعة التقرير / PDF')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->extraAttributes([
                    'onclick' => 'window.print(); return false;',
                ]),
        ];
    }

    public function mount(): void
    {
        $this->applyPeriodDates();
    }

    public function updatedPeriod(): void
    {
        $this->applyPeriodDates();
    }

    protected function applyPeriodDates(): void
    {
        switch ($this->period) {
            case 'today':
                $this->dateFrom = now()->startOfDay()->format('Y-m-d');
                $this->dateUntil = now()->endOfDay()->format('Y-m-d');
                break;
            case 'this_week':
                $this->dateFrom = now()->startOfWeek()->format('Y-m-d');
                $this->dateUntil = now()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
            default:
                $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
                $this->dateUntil = now()->endOfMonth()->format('Y-m-d');
                break;
        }
    }

    /**
     * Calculate report data based on selected date range.
     *
     * @return array<string, mixed>
     */
    public function getReportData(): array
    {
        $user = Auth::user();

        $fromDate = Carbon::parse($this->dateFrom ?? now()->startOfMonth())->startOfDay();
        $untilDate = Carbon::parse($this->dateUntil ?? now()->endOfMonth())->endOfDay();

        // Scope sales reps according to auth user role
        $salesQuery = User::where('role', UserRole::Sales->value)->where('is_active', true);
        if ($user && $user->isTeamLeader()) {
            $salesQuery->where('team_leader_id', $user->id);
        }
        $salesReps = $salesQuery->get();

        // Base customer query for period
        $customerQuery = Customer::query()
            ->whereBetween('created_at', [$fromDate, $untilDate]);
        if ($user && $user->isTeamLeader()) {
            $customerQuery->where('team_leader_id', $user->id);
        }

        $totalNewCustomers = (clone $customerQuery)->count();

        // Base activity query for period
        $activityQuery = CustomerActivity::query()
            ->whereHas('customer')
            ->whereBetween('created_at', [$fromDate, $untilDate]);
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
            $firstAct = $c->activities->sortBy('created_at')->first();
            if ($firstAct && $c->created_at && $firstAct->created_at) {
                $minutes = abs($c->created_at->diffInMinutes($firstAct->created_at, false));
                $totalResponseHours += ($minutes / 60.0);
                $responseCount++;
            }
        }

        $avgResponseHours = $responseCount > 0 ? round($totalResponseHours / $responseCount, 1) : 0;

        // Sales Reps Detailed Table & Leaderboard
        $salesPerformance = [];
        foreach ($salesReps as $rep) {
            $repCustomers = Customer::where('sales_id', $rep->id)
                ->whereBetween('created_at', [$fromDate, $untilDate]);

            $repNewCustomersCount = (clone $repCustomers)->count();

            $repActivitiesCount = CustomerActivity::whereHas('customer')
                ->where('user_id', $rep->id)
                ->whereBetween('created_at', [$fromDate, $untilDate])
                ->count();

            $repWonCount = (clone $repCustomers)
                ->whereIn('status_id', $wonStatusIds)
                ->count();

            $repConversionRate = $repNewCustomersCount > 0
                ? round(($repWonCount / $repNewCustomersCount) * 100, 1)
                : 0;

            // First response time per rep
            $repRespondedCusts = (clone $repCustomers)->whereHas('activities')->with(['activities' => fn ($q) => $q->oldest()])->get();
            $repTotalHours = 0;
            $repRespCount = 0;

            foreach ($repRespondedCusts as $rc) {
                $fa = $rc->activities->sortBy('created_at')->first();
                if ($fa && $rc->created_at && $fa->created_at) {
                    $minutes = abs($rc->created_at->diffInMinutes($fa->created_at, false));
                    $repTotalHours += ($minutes / 60.0);
                    $repRespCount++;
                }
            }

            $repAvgResponseHours = $repRespCount > 0 ? round($repTotalHours / $repRespCount, 1) : 0;

            $salesPerformance[] = [
                'id' => $rep->id,
                'name' => $rep->name,
                'assigned_count' => $repNewCustomersCount,
                'activities_count' => $repActivitiesCount,
                'won_count' => $repWonCount,
                'conversion_rate' => $repConversionRate,
                'avg_response_hours' => $repAvgResponseHours,
            ];
        }

        // Sort sales performance by won count & activities for Leaderboard
        usort($salesPerformance, fn ($a, $b) => $b['won_count'] <=> $a['won_count'] ?: $b['activities_count'] <=> $a['activities_count']);

        // Platforms breakdown
        $platforms = Platform::where('is_active', true)->get();
        $platformsBreakdown = [];

        foreach ($platforms as $platform) {
            $count = (clone $customerQuery)->where('platform_id', $platform->id)->count();
            $percentage = $totalNewCustomers > 0 ? round(($count / $totalNewCustomers) * 100, 1) : 0;

            $platformsBreakdown[] = [
                'name' => $platform->name,
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        $unassignedPlatformCount = (clone $customerQuery)->whereNull('platform_id')->count();
        if ($unassignedPlatformCount > 0) {
            $platformsBreakdown[] = [
                'name' => 'غير محدد / مباشر',
                'count' => $unassignedPlatformCount,
                'percentage' => $totalNewCustomers > 0 ? round(($unassignedPlatformCount / $totalNewCustomers) * 100, 1) : 0,
            ];
        }

        // Customer Needs breakdown
        $needs = CustomerNeed::where('is_active', true)->get();
        $needsBreakdown = [];

        foreach ($needs as $need) {
            $count = (clone $customerQuery)->where('customer_need_id', $need->id)->count();
            $percentage = $totalNewCustomers > 0 ? round(($count / $totalNewCustomers) * 100, 1) : 0;

            $needsBreakdown[] = [
                'name' => $need->name,
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        $unassignedNeedCount = (clone $customerQuery)->whereNull('customer_need_id')->count();
        if ($unassignedNeedCount > 0) {
            $needsBreakdown[] = [
                'name' => 'غير محدد',
                'count' => $unassignedNeedCount,
                'percentage' => $totalNewCustomers > 0 ? round(($unassignedNeedCount / $totalNewCustomers) * 100, 1) : 0,
            ];
        }

        return [
            'period_label' => match ($this->period) {
                'today' => 'اليوم (' . $fromDate->format('d/m/Y') . ')',
                'this_week' => 'هذا الأسبوع (' . $fromDate->format('d/m/Y') . ' - ' . $untilDate->format('d/m/Y') . ')',
                'this_month' => 'الشهر الحالي (' . $fromDate->format('01/m/Y') . ' - ' . $untilDate->format('t/m/Y') . ')',
                default => 'الفترة المحددة (' . $fromDate->format('d/m/Y') . ' - ' . $untilDate->format('d/m/Y') . ')',
            },
            'date_from' => $fromDate->format('d/m/Y'),
            'date_until' => $untilDate->format('d/m/Y'),
            'total_new_customers' => $totalNewCustomers,
            'total_activities' => $totalActivities,
            'won_customers_count' => $wonCustomersCount,
            'overall_conversion_rate' => $overallConversionRate,
            'avg_response_hours' => $avgResponseHours,
            'sales_performance' => $salesPerformance,
            'platforms_breakdown' => $platformsBreakdown,
            'needs_breakdown' => $needsBreakdown,
        ];
    }
}
