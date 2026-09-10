<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Enums\ActivityType;
use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Services\AuditService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'ملف العميل';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('تعديل'),

            Actions\Action::make('whatsapp')
                ->label('واتساب')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn (Customer $record) => $record->whatsappLink())
                ->openUrlInNewTab(),

            Actions\Action::make('add_activity')
                ->label('إضافة نشاط')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('activity_type')
                        ->label('نوع النشاط')
                        ->options(
                            collect(ActivityType::cases())
                                ->mapWithKeys(fn (ActivityType $type) => [$type->value => $type->label()])
                                ->toArray()
                        )
                        ->required(),

                    Forms\Components\Select::make('new_status_id')
                        ->label('تغيير الحالة إلى')
                        ->relationship('status', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('sort_order'))
                        ->nullable()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(3),

                    Forms\Components\DateTimePicker::make('follow_up_date')
                        ->label('موعد المتابعة التالية')
                        ->nullable()
                        ->native(false),
                ])
                ->action(function (Customer $record, array $data) {
                    $oldStatusId = $record->status_id;

                    CustomerActivity::create([
                        'customer_id' => $record->id,
                        'user_id' => Auth::id(),
                        'activity_type' => $data['activity_type'],
                        'old_status_id' => $oldStatusId,
                        'new_status_id' => $data['new_status_id'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'follow_up_date' => $data['follow_up_date'] ?? null,
                    ]);

                    if ($data['new_status_id'] && $data['new_status_id'] !== $oldStatusId) {
                        $record->update([
                            'status_id' => $data['new_status_id'],
                            'next_follow_up_at' => $data['follow_up_date'] ?? $record->next_follow_up_at,
                        ]);
                        AuditService::statusChanged($record, $oldStatusId, $data['new_status_id']);
                    } elseif ($data['follow_up_date']) {
                        $record->update(['next_follow_up_at' => $data['follow_up_date']]);
                    }

                    Notification::make()->title('تم إضافة النشاط')->success()->send();
                }),

            Actions\DeleteAction::make()
                ->label('حذف')
                ->visible(fn () => Auth::user()?->can('delete', $this->record)),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('معلومات العميل')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('الاسم'),

                        \Filament\Infolists\Components\TextEntry::make('phone')
                            ->label('الهاتف')
                            ->copyable()
                            ->icon('heroicon-o-phone'),

                        \Filament\Infolists\Components\TextEntry::make('whatsapp_phone')
                            ->label('واتساب')
                            ->copyable()
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->placeholder('—'),

                        \Filament\Infolists\Components\TextEntry::make('platform.name')
                            ->label('المصدر')
                            ->badge()
                            ->placeholder('—'),

                        \Filament\Infolists\Components\TextEntry::make('customerNeed.name')
                            ->label('الاحتياج')
                            ->badge()
                            ->color('info')
                            ->placeholder('—'),

                        \Filament\Infolists\Components\TextEntry::make('status.name')
                            ->label('الحالة')
                            ->badge()
                            ->color(fn ($record) => $record->status?->color ?? 'gray')
                            ->placeholder('—'),
                    ])
                    ->columns(3),

                Section::make('التعيين')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('sales.name')
                            ->label('المندوب'),

                        \Filament\Infolists\Components\TextEntry::make('teamLeader.name')
                            ->label('مدير الفريق'),

                        \Filament\Infolists\Components\TextEntry::make('next_follow_up_at')
                            ->label('موعد المتابعة التالية')
                            ->dateTime('d/m/Y H:i')
                            ->color(fn ($record) => match (true) {
                                $record->next_follow_up_at === null => null,
                                $record->next_follow_up_at->isPast() => 'danger',
                                $record->next_follow_up_at->isToday() => 'warning',
                                default => 'success',
                            })
                            ->placeholder('—'),

                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('تاريخ الإضافة')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(4),

                Section::make('التفاصيل')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('details')
                            ->label('التفاصيل والملاحظات')
                            ->columnSpanFull()
                            ->placeholder('لا توجد تفاصيل'),
                    ])
                    ->collapsible(),
            ]);
    }
}
