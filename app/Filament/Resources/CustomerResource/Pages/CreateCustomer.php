<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\User;
use App\Services\AuditService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        // Notify relevant users upon customer creation
        $recipients = collect();

        if ($this->record->sales) {
            $recipients->push($this->record->sales);
        }
        if ($this->record->teamLeader) {
            $recipients->push($this->record->teamLeader);
        }

        $allUsers = User::all();
        $admins = $allUsers->filter(fn (User $u) => $u->isAdmin());
        $recipients = $recipients->merge($admins)->unique('id');

        $creatorName = Auth::user()?->name ?? 'النظام';

        foreach ($recipients as $recipient) {
            $notif = Notification::make()
                ->title('عميل جديد: ' . $this->record->name)
                ->body("تم إضافة عميل جديد برقم ({$this->record->phone}) بواسطة {$creatorName}.")
                ->icon('heroicon-o-user-plus')
                ->success()
                ->actions([
                    Action::make('view')
                        ->label('عرض العميل')
                        ->url(CustomerResource::getUrl('view', ['record' => $this->record])),
                ]);

            DB::table('notifications')->insert([
                'id' => Str::orderedUuid()->toString(),
                'type' => 'Filament\Notifications\Notification',
                'notifiable_type' => $recipient->getMorphClass(),
                'notifiable_id' => $recipient->id,
                'data' => json_encode(array_merge($notif->toArray(), [
                    'duration' => 'persistent',
                    'format' => 'filament',
                ])),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
