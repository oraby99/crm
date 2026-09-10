<?php

namespace App\Filament\Resources\CustomerNeedResource\Pages;

use App\Filament\Resources\CustomerNeedResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomerNeeds extends ManageRecords
{
    protected static string $resource = CustomerNeedResource::class;

    protected static ?string $title = 'احتياجات العملاء';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('إضافة احتياج'),
        ];
    }
}
