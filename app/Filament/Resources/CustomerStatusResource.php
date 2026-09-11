<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerStatusResource\Pages;
use App\Models\CustomerStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CustomerStatusResource extends Resource
{
    protected static ?string $model = CustomerStatus::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'حالات العملاء';

    protected static ?string $modelLabel = 'حالة';

    protected static ?string $pluralModelLabel = 'حالات العملاء';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('color')
                    ->label('اللون')
                    ->options([
                        'gray' => 'رمادي',
                        'warning' => 'تحذيري',
                        'info' => 'معلوماتي',
                        'primary' => 'رئيسي',
                        'success' => 'نجاح',
                        'danger' => 'خطر',
                    ])
                    ->default('gray')
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),

                Forms\Components\Toggle::make('is_final')
                    ->label('حالة نهائية')
                    ->helperText('إذا كانت نهائية، لن يمكن تغيير الحالة منها.')
                    ->default(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->badge()
                    ->color(fn (CustomerStatus $record) => $record->color)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_final')
                    ->label('نهائية')
                    ->boolean(),

                Tables\Columns\TextColumn::make('customers_count')
                    ->label('عدد العملاء')
                    ->counts('customers')
                    ->badge()
                    ->color('primary'),
            ])
            ->actions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ])
            ->defaultSort('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCustomerStatuses::route('/'),
        ];
    }
}
