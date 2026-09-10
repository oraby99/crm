<?php

namespace App\Filament\Resources\ImportResource\Pages;

use App\Filament\Resources\ImportResource;
use App\Imports\CustomersImport;
use App\Models\Import;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListImports extends ListRecords
{
    protected static string $resource = ImportResource::class;

    protected static ?string $title = 'عمليات الاستيراد';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('استيراد ملف جديد')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    FileUpload::make('file')
                        ->label('ملف الإكسيل')
                        ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                        ->required()
                        ->storeFiles(true)
                        ->directory('imports'),
                ])
                ->action(function (array $data) {
                    $filePath = $data['file'];
                    
                    // Create import record
                    $import = Import::create([
                        'uploaded_by' => Auth::id(),
                        'file_name' => basename($filePath),
                        'file_path' => $filePath,
                        'status' => \App\Enums\ImportStatus::Pending->value,
                    ]);

                    try {
                        Excel::import(new CustomersImport($import), Storage::path($filePath));
                        
                        Notification::make()
                            ->title('تم الاستيراد بنجاح')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        $import->update([
                            'status' => \App\Enums\ImportStatus::Failed->value,
                            'error_log' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->title('حدث خطأ أثناء الاستيراد')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
