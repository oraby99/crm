<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\User;
use App\Services\AuditService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'تعديل بيانات العميل';

    /**
     * @var array<string, mixed>
     */
    private array $originalData = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->label('عرض'),
            Actions\DeleteAction::make()->label('حذف'),
            Actions\RestoreAction::make()->label('استعادة'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalData = $this->record->only([
            'name', 'phone', 'status_id', 'sales_id', 'team_leader_id',
        ]);

        if (isset($data['sales_id'])) {
            $salesUser = User::find($data['sales_id']);
            $data['team_leader_id'] = $salesUser?->team_leader_id;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $newData = $this->record->only(['name', 'phone', 'status_id', 'sales_id', 'team_leader_id']);
        $changedData = array_diff_assoc($newData, $this->originalData);

        if (! empty($changedData)) {
            // Log status change separately.
            if (array_key_exists('status_id', $changedData)) {
                AuditService::statusChanged(
                    $this->record,
                    $this->originalData['status_id'] ?? null,
                    $newData['status_id'] ?? null
                );
            }

            // Log assignment changes.
            if (array_key_exists('sales_id', $changedData)) {
                AuditService::customerAssigned(
                    $this->record,
                    ['sales_id' => $this->originalData['sales_id'] ?? null],
                    ['sales_id' => $newData['sales_id'] ?? null]
                );
            }

            AuditService::customerUpdated($this->record, $this->originalData, $newData);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
