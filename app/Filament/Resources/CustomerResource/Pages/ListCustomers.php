<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'العملاء';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('إضافة عميل'),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        if (request()->has('alert_not_contacted')) {
            $this->activeTab = 'not_contacted';
        }

        if (request()->has('alert_sales_id')) {
            // In Filament 3, tableFilters is an array of state
            $this->tableFilters['sales_id'] = ['value' => request('alert_sales_id')];
        }
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),

            'follow_up_today' => Tab::make('متابعة اليوم')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('next_follow_up_at', today()))
                ->badge(fn () => CustomerResource::getEloquentQuery()->whereDate('next_follow_up_at', today())->count())
                ->badgeColor('warning'),

            'overdue' => Tab::make('متأخرة')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('next_follow_up_at', '<', today()))
                ->badge(fn () => CustomerResource::getEloquentQuery()->whereDate('next_follow_up_at', '<', today())->count())
                ->badgeColor('danger'),

            'not_contacted' => Tab::make('لم يتم التواصل')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('activities'))
                ->badge(fn () => CustomerResource::getEloquentQuery()->whereDoesntHave('activities')->count())
                ->badgeColor('gray'),
        ];
    }
}
