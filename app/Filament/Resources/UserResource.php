<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\AuditService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة النظام';

    protected static ?int $navigationSort = 1;

    // ──────────────────────────────────────────────────────────────
    // Access control
    // ──────────────────────────────────────────────────────────────

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() || Auth::user()?->isTeamLeader();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);

        if ($user?->isAdmin()) {
            return $query;
        }

        if ($user?->isTeamLeader()) {
            // Team leaders see only their own sales employees.
            return $query->where('team_leader_id', $user->id)
                ->where('role', UserRole::Sales->value);
        }

        // Sales employees should not see this page.
        return $query->whereRaw('0 = 1');
    }

    // ──────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        $authUser = Auth::user();
        $isAdmin = $authUser?->isAdmin();

        return $schema
            ->schema([
                Section::make('معلومات المستخدم')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\Select::make('role')
                            ->label('الصلاحية')
                            ->options(function () use ($isAdmin) {
                                if ($isAdmin) {
                                    return collect(UserRole::cases())
                                        ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])
                                        ->toArray();
                                }

                                // Team leaders can only create sales employees.
                                return [UserRole::Sales->value => UserRole::Sales->label()];
                            })
                            ->required()
                            ->default(UserRole::Sales->value)
                            ->live()
                            ->disabled(! $isAdmin && $form->getOperation() === 'edit'),

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
                            ->visible(fn ($get) => $get('role') === UserRole::Sales->value)
                            ->default(fn () => $isAdmin ? null : Auth::id()),

                        Forms\Components\Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('كلمة المرور')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('تأكيد كلمة المرور')
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('الصلاحية')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state) => $state->label())
                    ->color(fn (UserRole $state) => $state->color()),

                Tables\Columns\TextColumn::make('teamLeader.name')
                    ->label('مدير الفريق')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function (User $record, bool $state) {
                        AuditService::userStatusChanged($record, ! $state, $state);

                        Notification::make()
                            ->title($state ? 'تم تفعيل الحساب' : 'تم تعطيل الحساب')
                            ->success()
                            ->send();
                    }),

                Tables\Columns\TextColumn::make('assignedCustomers_count')
                    ->label('العملاء')
                    ->counts('assignedCustomers')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('الصلاحية')
                    ->options(
                        collect(UserRole::cases())
                            ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])
                            ->toArray()
                    ),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط')
                    ->placeholder('الكل'),

                Tables\Filters\SelectFilter::make('team_leader_id')
                    ->label('مدير الفريق')
                    ->relationship('teamLeader', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => Auth::user()?->isAdmin()),

                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => Auth::user()?->isAdmin()),
            ])
            ->actions([
                EditAction::make()
                    ->label('تعديل'),

                Action::make('activate')
                    ->label('تفعيل')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record) => ! $record->is_active)
                    ->action(function (User $record) {
                        $record->update(['is_active' => true]);
                        AuditService::userStatusChanged($record, false, true);
                        Notification::make()->title('تم تفعيل الحساب')->success()->send();
                    })
                    ->requiresConfirmation(false),

                Action::make('deactivate')
                    ->label('تعطيل')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (User $record) => $record->is_active && $record->id !== Auth::id())
                    ->action(function (User $record) {
                        $record->update(['is_active' => false]);
                        AuditService::userStatusChanged($record, true, false);
                        Notification::make()->title('تم تعطيل الحساب')->warning()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('تعطيل الحساب')
                    ->modalDescription('هل تريد تعطيل هذا الحساب؟ لن يتمكن المستخدم من تسجيل الدخول.'),

                \Filament\Actions\DeleteAction::make()
                    ->label('حذف'),

                \Filament\Actions\RestoreAction::make()
                    ->label('استعادة'),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()->label('حذف المحدد'),
                    \Filament\Actions\RestoreBulkAction::make()->label('استعادة المحدد'),
                    \Filament\Actions\ForceDeleteBulkAction::make()->label('حذف نهائي'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ──────────────────────────────────────────────────────────────
    // Pages
    // ──────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
