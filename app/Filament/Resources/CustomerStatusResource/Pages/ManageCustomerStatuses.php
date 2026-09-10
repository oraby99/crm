<?php

namespace App\Filament\Resources\CustomerStatusResource\Pages;

use App\Filament\Resources\CustomerStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomerStatuses extends ManageRecords
{
    protected static string $resource = CustomerStatusResource::class;

    protected static ?string $title = 'حالات العملاء';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('إضافة حالة'),
        ];
    }
}
