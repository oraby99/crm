<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\User;
use App\Services\AuditService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'إضافة عميل';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        // If the authenticated user is a sales employee, auto-assign to them.
        if (Auth::user()?->isSales()) {
            $data['sales_id'] = Auth::id();
            $data['team_leader_id'] = Auth::user()->team_leader_id;
        } elseif (isset($data['sales_id'])) {
            $salesUser = User::find($data['sales_id']);
            $data['team_leader_id'] = $salesUser?->team_leader_id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        AuditService::customerCreated($this->record, $this->record->only([
            'name', 'phone', 'platform_id', 'customer_need_id', 'sales_id', 'team_leader_id',
        ]));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
