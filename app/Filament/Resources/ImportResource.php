<?php

namespace App\Filament\Resources;

use App\Enums\ImportStatus;
use App\Filament\Resources\ImportResource\Pages;
use App\Models\Import;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ImportResource extends Resource
{
    protected static ?string $model = Import::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?string $navigationLabel = 'استيراد العملاء';

    protected static ?string $modelLabel = 'عملية استيراد';

    protected static ?string $pluralModelLabel = 'عمليات الاستيراد';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة العملاء';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() || Auth::user()?->isTeamLeader();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('uploader');
        
        $user = Auth::user();
        if ($user && $user->isTeamLeader()) {
            $query->where('uploaded_by', $user->id);
        }

        return $query->latest();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_name')
                    ->label('اسم الملف')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('بواسطة')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (ImportStatus $state) => match ($state) {
                        ImportStatus::Pending => 'gray',
                        ImportStatus::Processing => 'warning',
                        ImportStatus::Completed => 'success',
                        ImportStatus::Failed => 'danger',
                    })
                    ->formatStateUsing(fn (ImportStatus $state) => $state->label()),

                Tables\Columns\TextColumn::make('total_rows')
                    ->label('الإجمالي')
                    ->sortable(),

                Tables\Columns\TextColumn::make('successful_rows')
                    ->label('نجاح')
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('failed_rows')
                    ->label('فشل')
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\Action::make('view_errors')
                    ->label('عرض الأخطاء')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (Import $record) => !empty($record->error_log))
                    ->modalContent(fn (Import $record) => view('filament.modals.import-errors', ['log' => $record->error_log]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),
                    
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->poll('10s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImports::route('/'),
        ];
    }
}
