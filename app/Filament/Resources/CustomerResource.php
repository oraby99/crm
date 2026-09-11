<?php

namespace App\Filament\Resources;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\ActivitiesRelationManager;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomerStatus;
use App\Models\User;
use App\Services\AuditService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'العملاء';

    protected static ?string $modelLabel = 'عميل';

    protected static ?string $pluralModelLabel = 'العملاء';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة العملاء';

    protected static ?int $navigationSort = 1;

    // ──────────────────────────────────────────────────────────────
    // Access control
    // ──────────────────────────────────────────────────────────────

    public static function canAccess(): bool
    {
        return Auth::user()?->is_active ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['sales', 'teamLeader', 'status', 'platform', 'customerNeed']);
    }

    // ──────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        $authUser = Auth::user();
        $isAdmin = $authUser?->isAdmin();
        $isTeamLeader = $authUser?->isTeamLeader();

        return $schema
            ->schema([
                Section::make('معلومات العميل')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم العميل')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->required()
                            ->tel()
                            ->maxLength(30)
                            ->live(onBlur: true)
                            ->helperText(function ($state, ?\Illuminate\Database\Eloquent\Model $record) {
                                if (blank($state)) {
                                    return null;
                                }

                                $existingCustomer = \App\Models\Customer::where('phone', $state)
                                    ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                                    ->with('sales')
                                    ->first();

                                if ($existingCustomer) {
                                    $salesName = $existingCustomer->sales ? $existingCustomer->sales->name : 'الإدارة (بدون مندوب محدد)';

                                    return new \Illuminate\Support\HtmlString(
                                        '<span class="text-danger-600 dark:text-danger-400 text-sm font-medium">رقم الهاتف مسجل بالفعل مع المندوب: '.e($salesName).'</span>'
                                    );
                                }

                                return null;
                            }),

                        Forms\Components\TextInput::make('whatsapp_phone')
                            ->label('رقم الواتساب')
                            ->tel()
                            ->nullable()
                            ->maxLength(30),

                        Forms\Components\Select::make('platform_id')
                            ->label('المصدر / المنصة')
                            ->relationship(
                                'platform',
                                'name',
                                fn (Builder $query) => $query->where('is_active', true)->orderBy('id')
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('customer_need_id')
                            ->label('احتياج العميل')
                            ->relationship(
                                'customerNeed',
                                'name',
                                fn (Builder $query) => $query->where('is_active', true)->orderBy('id')
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('status_id')
                            ->label('الحالة')
                            ->relationship(
                                'status',
                                'name',
                                fn (Builder $query) => $query->where('is_active', true)->orderBy('id')
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('التعيين')
                    ->schema([
                        Forms\Components\Select::make('sales_id')
                            ->label('مندوب المبيعات')
                            ->options(function () use ($authUser, $isAdmin, $isTeamLeader) {
                                if ($isAdmin) {
                                    return User::where('role', UserRole::Sales->value)
                                        ->where('is_active', true)
                                        ->pluck('name', 'id');
                                }

                                if ($isTeamLeader) {
                                    return User::where('team_leader_id', $authUser->id)
                                        ->where('role', UserRole::Sales->value)
                                        ->where('is_active', true)
                                        ->pluck('name', 'id');
                                }

                                return [$authUser->id => $authUser->name];
                            })
                            ->searchable()
                            ->required()
                            ->default(fn () => $authUser?->isSales() ? $authUser->id : null)
                            ->disabled(fn () => $authUser?->isSales())
                            ->live()
                            ->afterStateUpdated(function ($set, ?int $state) {
                                if ($state) {
                                    $salesUser = User::find($state);
                                    $set('team_leader_id', $salesUser?->team_leader_id);
                                }
                            }),

                        Forms\Components\Select::make('team_leader_id')
                            ->label('مدير الفريق')
                            ->relationship(
                                'teamLeader',
                                'name',
                                fn (Builder $query) => $query->where('role', UserRole::TeamLeader->value)->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabled(! $isAdmin),

                        Forms\Components\DateTimePicker::make('next_follow_up_at')
                            ->label('موعد المتابعة التالية')
                            ->nullable()
                            ->native(false),
                    ])
                    ->columns(2)
                    ->visible(fn () => $isAdmin || $isTeamLeader || $authUser?->isSales()),

                Section::make('تفاصيل إضافية')
                    ->schema([
                        Forms\Components\Textarea::make('details')
                            ->label('التفاصيل والملاحظات')
                            ->rows(4)
                            ->nullable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        $authUser = Auth::user();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم العميل')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('platform.name')
                    ->label('المصدر')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('customerNeed.name')
                    ->label('الاحتياج')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status.name')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (Customer $record) => $record->status?->color ?? 'gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sales.name')
                    ->label('المندوب')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->visible(fn () => $authUser?->isAdmin() || $authUser?->isTeamLeader()),

                Tables\Columns\TextColumn::make('teamLeader.name')
                    ->label('مدير الفريق')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->visible(fn () => $authUser?->isAdmin())
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('activities_count')
                    ->label('تم التواصل')
                    ->counts('activities')
                    ->icon(fn ($state) => $state > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->tooltip(fn ($state) => $state > 0 ? "تم التواصل ({$state} نشاط)" : 'لم يتم التواصل بعد'),

                Tables\Columns\TextColumn::make('latest_follow_up')
                    ->label('أحدث متابعة')
                    ->state(fn (Customer $record) => $record->getLatestFollowUpNotes())
                    ->tooltip(fn (Customer $record) => $record->getLatestFollowUpSummary())
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('first_follow_up')
                    ->label('المتابعة الأولى')
                    ->state(fn (Customer $record) => $record->getFollowUpNotes(1))
                    ->tooltip(fn (Customer $record) => $record->getFollowUpSummary(1))
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('second_follow_up')
                    ->label('المتابعة الثانية')
                    ->state(fn (Customer $record) => $record->getFollowUpNotes(2))
                    ->tooltip(fn (Customer $record) => $record->getFollowUpSummary(2))
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('third_follow_up')
                    ->label('المتابعة الثالثة')
                    ->state(fn (Customer $record) => $record->getFollowUpNotes(3))
                    ->tooltip(fn (Customer $record) => $record->getFollowUpSummary(3))
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fourth_follow_up')
                    ->label('المتابعة الرابعة')
                    ->state(fn (Customer $record) => $record->getFollowUpNotes(4))
                    ->tooltip(fn (Customer $record) => $record->getFollowUpSummary(4))
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fifth_follow_up')
                    ->label('المتابعة الخامسة')
                    ->state(fn (Customer $record) => $record->getFollowUpNotes(5))
                    ->tooltip(fn (Customer $record) => $record->getFollowUpSummary(5))
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('all_follow_ups')
                    ->label('جميع المتابعات')
                    ->state(fn (Customer $record) => $record->getAllFollowUpsSummary())
                    ->placeholder('—')
                    ->wrap()
                    ->limit(100)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('next_follow_up_at')
                    ->label('موعد المتابعة')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color(fn (Customer $record) => match (true) {
                        $record->next_follow_up_at === null => null,
                        $record->next_follow_up_at->isPast() => 'danger',
                        $record->next_follow_up_at->isToday() => 'warning',
                        default => 'success',
                    })
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status_id')
                    ->label('الحالة')
                    ->relationship('status', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('platform_id')
                    ->label('المصدر')
                    ->relationship('platform', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('customer_need_id')
                    ->label('الاحتياج')
                    ->relationship('customerNeed', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('sales_id')
                    ->label('المندوب')
                    ->relationship('sales', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => $authUser?->isAdmin() || $authUser?->isTeamLeader()),

                Tables\Filters\SelectFilter::make('team_leader_id')
                    ->label('مدير الفريق')
                    ->relationship('teamLeader', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => $authUser?->isAdmin()),

                Tables\Filters\Filter::make('not_contacted')
                    ->label('لم يتم التواصل')
                    ->query(fn (Builder $query) => $query->whereDoesntHave('activities'))
                    ->toggle(),

                Tables\Filters\Filter::make('follow_up_today')
                    ->label('متابعة اليوم')
                    ->query(fn (Builder $query) => $query->whereDate('next_follow_up_at', today()))
                    ->toggle(),

                Tables\Filters\Filter::make('overdue')
                    ->label('متأخرة')
                    ->query(fn (Builder $query) => $query->whereDate('next_follow_up_at', '<', today()))
                    ->toggle(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('من تاريخ'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => $authUser?->isAdmin() || $authUser?->isTeamLeader()),
            ])
            ->actions([
                ViewAction::make()
                    ->label('عرض'),

                EditAction::make()
                    ->label('تعديل'),

                Action::make('whatsapp')
                    ->label('واتساب')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Customer $record) => $record->whatsappLink())
                    ->openUrlInNewTab(),

                Action::make('add_activity')
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

                \Filament\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn (Customer $record) => Auth::user()?->can('delete', $record)),

                \Filament\Actions\RestoreAction::make()
                    ->label('استعادة'),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('assign_to_sales')
                        ->label('تعيين لمندوب')
                        ->icon('heroicon-o-user')
                        ->color('primary')
                        ->visible(fn () => $authUser?->isAdmin() || $authUser?->isTeamLeader())
                        ->form([
                            Forms\Components\Select::make('sales_id')
                                ->label('المندوب')
                                ->options(function () use ($authUser) {
                                    if ($authUser?->isAdmin()) {
                                        return User::where('role', UserRole::Sales->value)
                                            ->where('is_active', true)
                                            ->pluck('name', 'id');
                                    }

                                    return User::where('team_leader_id', $authUser->id)
                                        ->where('role', UserRole::Sales->value)
                                        ->where('is_active', true)
                                        ->pluck('name', 'id');
                                })
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function ($records, array $data) {
                            $salesUser = User::find($data['sales_id']);
                            $records->each(function (Customer $customer) use ($salesUser) {
                                $oldSalesId = $customer->sales_id;
                                $customer->update([
                                    'sales_id' => $salesUser->id,
                                    'team_leader_id' => $salesUser->team_leader_id,
                                ]);
                                AuditService::customerAssigned($customer, ['sales_id' => $oldSalesId], ['sales_id' => $salesUser->id]);
                            });
                            Notification::make()->title('تم التعيين بنجاح')->success()->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    \Filament\Actions\BulkAction::make('change_status')
                        ->label('تغيير الحالة')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status_id')
                                ->label('الحالة الجديدة')
                                ->options(
                                    CustomerStatus::where('is_active', true)->orderBy('id')->pluck('name', 'id')
                                )
                                ->required(),

                            Forms\Components\Textarea::make('notes')
                                ->label('ملاحظات')
                                ->rows(2),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(function (Customer $customer) use ($data) {
                                $oldStatusId = $customer->status_id;
                                $customer->update(['status_id' => $data['status_id']]);

                                CustomerActivity::create([
                                    'customer_id' => $customer->id,
                                    'user_id' => Auth::id(),
                                    'activity_type' => ActivityType::StatusChange->value,
                                    'old_status_id' => $oldStatusId,
                                    'new_status_id' => $data['status_id'],
                                    'notes' => $data['notes'] ?? null,
                                ]);

                                AuditService::statusChanged($customer, $oldStatusId, $data['status_id']);
                            });
                            Notification::make()->title('تم تغيير الحالة')->success()->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    \Filament\Actions\BulkAction::make('schedule_follow_up')
                        ->label('جدولة متابعة')
                        ->icon('heroicon-o-calendar')
                        ->form([
                            Forms\Components\DateTimePicker::make('next_follow_up_at')
                                ->label('موعد المتابعة')
                                ->required()
                                ->native(false),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(fn (Customer $customer) => $customer->update(['next_follow_up_at' => $data['next_follow_up_at']]));
                            Notification::make()->title('تم جدولة المتابعة')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    \Filament\Actions\DeleteBulkAction::make()
                        ->label('حذف المحدد')
                        ->visible(fn () => $authUser?->isAdmin() || $authUser?->isTeamLeader()),

                    \Filament\Actions\RestoreBulkAction::make()
                        ->label('استعادة المحدد'),

                    \Filament\Actions\BulkAction::make('export_csv')
                        ->label('تصدير المحدد (CSV)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $csvFileName = 'customers_' . now()->format('Y-m-d_H-i-s') . '.csv';

                            $headers = [
                                'Content-Type' => 'text/csv; charset=UTF-8',
                                'Content-Disposition' => "attachment; filename=\"{$csvFileName}\"",
                                'Pragma' => 'no-cache',
                                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                                'Expires' => '0',
                            ];

                            $callback = function () use ($records) {
                                $file = fopen('php://output', 'w');
                                fputs($file, "\xEF\xBB\xBF");

                                // Dynamically calculate max follow-up count among selected records
                                $maxFollowUps = 0;
                                foreach ($records as $customer) {
                                    $count = $customer->activities()->count();
                                    if ($count > $maxFollowUps) {
                                        $maxFollowUps = $count;
                                    }
                                }

                                $csvHeaders = [
                                    'ID',
                                    'اسم العميل',
                                    'رقم الهاتف',
                                    'رقم الواتساب',
                                    'المصدر',
                                    'الاحتياج',
                                    'الحالة',
                                    'مندوب المبيعات',
                                    'مدير الفريق',
                                    'التفاصيل والملاحظات',
                                ];

                                for ($i = 1; $i <= $maxFollowUps; $i++) {
                                    $csvHeaders[] = "المتابعة رقم {$i}";
                                }

                                $csvHeaders[] = 'أحدث متابعة';
                                $csvHeaders[] = 'سجل جميع المتابعات';
                                $csvHeaders[] = 'موعد المتابعة التالية';
                                $csvHeaders[] = 'تاريخ الإضافة';

                                fputcsv($file, $csvHeaders);

                                foreach ($records as $customer) {
                                    $followUpsMap = $customer->getFormattedFollowUpsArray();

                                    $row = [
                                        $customer->id,
                                        $customer->name,
                                        $customer->phone,
                                        $customer->whatsapp_phone ?? '',
                                        $customer->platform?->name ?? '',
                                        $customer->customerNeed?->name ?? '',
                                        $customer->status?->name ?? '',
                                        $customer->sales?->name ?? '',
                                        $customer->teamLeader?->name ?? '',
                                        $customer->details ?? '',
                                    ];

                                    for ($i = 1; $i <= $maxFollowUps; $i++) {
                                        $row[] = $followUpsMap[$i] ?? '';
                                    }

                                    $row[] = $customer->getLatestFollowUpSummary() ?? '';
                                    $row[] = $customer->getAllFollowUpsSummary() ?? '';
                                    $row[] = $customer->next_follow_up_at?->format('Y-m-d H:i') ?? '';
                                    $row[] = $customer->created_at?->format('Y-m-d H:i') ?? '';

                                    fputcsv($file, $row);
                                }

                                fclose($file);
                            };

                            return response()->streamDownload($callback, $csvFileName, $headers);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    // ──────────────────────────────────────────────────────────────
    // Relation managers
    // ──────────────────────────────────────────────────────────────

    public static function getRelationManagers(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Pages
    // ──────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
