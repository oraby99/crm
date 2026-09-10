<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Enums\ActivityType;
use App\Models\CustomerActivity;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'سجل الأنشطة';

    protected static ?string $modelLabel = 'نشاط';

    protected static ?string $pluralModelLabel = 'الأنشطة';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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
                    ->relationship('newStatus', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('sort_order'))
                    ->nullable()
                    ->searchable()
                    ->preload(),

                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('follow_up_date')
                    ->label('موعد المتابعة')
                    ->nullable()
                    ->native(false),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('activity_type')
            ->columns([
                Tables\Columns\TextColumn::make('activity_type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (ActivityType $state) => $state->label())
                    ->color(fn (ActivityType $state) => $state->color()),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('المنفذ')
                    ->searchable(),

                Tables\Columns\TextColumn::make('newStatus.name')
                    ->label('الحالة الجديدة')
                    ->badge()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('الملاحظات')
                    ->limit(60)
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('follow_up_date')
                    ->label('موعد المتابعة')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('activity_type')
                    ->label('النوع')
                    ->options(
                        collect(ActivityType::cases())
                            ->mapWithKeys(fn (ActivityType $type) => [$type->value => $type->label()])
                            ->toArray()
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة نشاط')
                    ->mutateFormDataBeforeCreate(fn (array $data) => array_merge($data, [
                        'user_id' => Auth::id(),
                        'old_status_id' => $this->getOwnerRecord()->status_id,
                    ]))
                    ->after(function (CustomerActivity $record) {
                        // Update customer status if changed
                        if ($record->new_status_id && $record->new_status_id !== $this->getOwnerRecord()->status_id) {
                            $this->getOwnerRecord()->update(['status_id' => $record->new_status_id]);
                        }

                        // Update follow-up date if set
                        if ($record->follow_up_date) {
                            $this->getOwnerRecord()->update(['next_follow_up_at' => $record->follow_up_date]);
                        }
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->label('تعديل')
                    ->visible(fn (CustomerActivity $record) => $record->user_id === Auth::id() || Auth::user()?->isAdmin()),

                \Filament\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn () => Auth::user()?->isAdmin() || Auth::user()?->isTeamLeader()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
