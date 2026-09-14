<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Enums\ActivityType;
use App\Enums\CustomerType;
use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Services\AuditService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
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
                        ->relationship('status', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('id'))
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
                        TextEntry::make('name')
                            ->label('الاسم'),

                        TextEntry::make('phone')
                            ->label('الهاتف')
                            ->copyable()
                            ->icon('heroicon-o-phone'),

                        TextEntry::make('whatsapp_phone')
                            ->label('واتساب')
                            ->copyable()
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->placeholder('—'),

                        TextEntry::make('type')
                            ->label('التصنيف')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof CustomerType ? $state->label() : (CustomerType::tryFrom((string) $state)?->label() ?? 'عميل'))
                            ->color(fn ($state) => $state instanceof CustomerType ? $state->color() : (CustomerType::tryFrom((string) $state)?->color() ?? 'info'))
                            ->icon(fn ($state) => $state instanceof CustomerType ? $state->icon() : (CustomerType::tryFrom((string) $state)?->icon() ?? 'heroicon-o-user')),

                        TextEntry::make('platform.name')
                            ->label('المصدر')
                            ->badge()
                            ->placeholder('—'),

                        TextEntry::make('customerNeed.name')
                            ->label('الاحتياج')
                            ->badge()
                            ->color('info')
                            ->placeholder('—'),

                        TextEntry::make('status.name')
                            ->label('الحالة')
                            ->badge()
                            ->color(fn ($record) => $record->status?->color ?? 'gray')
                            ->placeholder('—'),
                    ])
                    ->columns(3),

                Section::make('التعيين')
                    ->schema([
                        TextEntry::make('sales.name')
                            ->label('المندوب'),

                        TextEntry::make('teamLeader.name')
                            ->label('مدير الفريق'),

                        TextEntry::make('next_follow_up_at')
                            ->label('موعد المتابعة التالية')
                            ->dateTime('d/m/Y H:i')
                            ->color(fn ($record) => match (true) {
                                $record->next_follow_up_at === null => null,
                                $record->next_follow_up_at->isPast() => 'danger',
                                $record->next_follow_up_at->isToday() => 'warning',
                                default => 'success',
                            })
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('تاريخ الإضافة')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(4),

                Section::make('سجل المتابعات التفصيلي')
                    ->icon('heroicon-o-document-text')
                    ->schema(function (Customer $record): array {
                        $activities = $record->activities()
                            ->reorder('id', 'asc')
                            ->with(['user', 'oldStatus', 'newStatus'])
                            ->get();

                        if ($activities->isEmpty()) {
                            return [
                                TextEntry::make('no_activities')
                                    ->hiddenLabel()
                                    ->default('لم يتم تسجيل أي متابعات أو أنشطة لهذا العميل بعد.')
                                    ->color('gray')
                                    ->columnSpanFull(),
                            ];
                        }

                        $activityData = [];
                        foreach ($activities as $i => $activity) {
                            $activityData[] = [
                                'num' => $i + 1,
                                'activity' => $activity,
                            ];
                        }

                        // Display newest first (descending), with correct chronological numbers
                        $activityData = array_reverse($activityData);
                        $totalCount = count($activityData);

                        $items = [];
                        foreach ($activityData as $item) {
                            $num = $item['num'];
                            /** @var CustomerActivity $activity */
                            $activity = $item['activity'];

                            $typeLabel = $activity->activity_type ? $activity->activity_type->label() : 'نشاط';
                            $typeColor = $activity->activity_type ? $activity->activity_type->color() : 'gray';
                            $dateStr = $activity->created_at ? $activity->created_at->format('d/m/Y H:i') : '';
                            $userName = $activity->user ? $activity->user->name : 'غير محدد';
                            $notes = $activity->notes ?: 'لا توجد ملاحظات';

                            $statusChange = null;
                            if ($activity->oldStatus || $activity->newStatus) {
                                $oldName = $activity->oldStatus?->name;
                                $newName = $activity->newStatus?->name;
                                if ($oldName && $newName && $oldName !== $newName) {
                                    $statusChange = "{$oldName} ➔ {$newName}";
                                } elseif ($newName) {
                                    $statusChange = $newName;
                                }
                            }

                            $nextDate = $activity->follow_up_date
                                ? $activity->follow_up_date->format('d/m/Y H:i')
                                : null;

                            $fields = [
                                TextEntry::make("act_{$activity->id}_user")
                                    ->label('منفذ المتابعة')
                                    ->default($userName)
                                    ->icon('heroicon-o-user'),

                                TextEntry::make("act_{$activity->id}_type")
                                    ->label('نوع النشاط')
                                    ->default($typeLabel)
                                    ->badge()
                                    ->color($typeColor),
                            ];

                            if ($statusChange) {
                                $fields[] = TextEntry::make("act_{$activity->id}_status")
                                    ->label('تغيير الحالة')
                                    ->default($statusChange)
                                    ->badge()
                                    ->color('info');
                            }

                            if ($nextDate) {
                                $fields[] = TextEntry::make("act_{$activity->id}_next")
                                    ->label('موعد المتابعة القادمة')
                                    ->default($nextDate)
                                    ->icon('heroicon-o-calendar')
                                    ->color('warning');
                            }

                            $fields[] = TextEntry::make("act_{$activity->id}_notes")
                                ->label('الملاحظات والتفاصيل')
                                ->default($notes)
                                ->columnSpanFull()
                                ->markdown();

                            $badgeTag = ($num === $totalCount) ? ' — (أحدث متابعة)' : '';

                            $items[] = Section::make("المتابعة رقم {$num}{$badgeTag} ({$dateStr})")
                                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                                ->compact()
                                ->schema($fields)
                                ->columns(3);
                        }

                        return $items;
                    })
                    ->collapsible(),

                Section::make('التفاصيل')
                    ->schema([
                        TextEntry::make('details')
                            ->label('التفاصيل والملاحظات')
                            ->columnSpanFull()
                            ->placeholder('لا توجد تفاصيل'),
                    ])
                    ->collapsible(),
            ]);
    }
}
