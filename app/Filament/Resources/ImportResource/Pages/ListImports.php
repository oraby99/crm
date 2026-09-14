<?php

namespace App\Filament\Resources\ImportResource\Pages;

use App\Enums\ImportStatus;
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
            Actions\Action::make('download_sample')
                ->label('تحميل نموذج إكسيل (Sample)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $samplePath = public_path('samples/sample_customers.xlsx');
                    if (file_exists($samplePath)) {
                        return response()->download($samplePath, 'نموذج_استيراد_العملاء.xlsx');
                    }

                    Notification::make()
                        ->title('ملف النموذج غير متوفر حالياً')
                        ->danger()
                        ->send();
                }),

            Actions\Action::make('import')
                ->label('استيراد ملف جديد')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    FileUpload::make('file')
                        ->label('ملف الإكسيل أو CSV')
                        ->helperText('يمكنك رفع ملف إكسيل عادي (.xlsx / .xls) أو ملف CSV بكل سهولة دون حاجة لأي تحويل.')
                        ->acceptedFileTypes([
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/excel',
                            'application/x-excel',
                            'application/x-msexcel',
                            'application/octet-stream',
                        ])
                        ->rules(['mimes:xlsx,xls,csv,txt'])
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
                        'status' => ImportStatus::Pending->value,
                    ]);

                    try {
                        if (! class_exists('\ZipArchive') && in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['xlsx', 'xls'])) {
                            throw new \Exception('تطوير ملفات الإكسيل (.xlsx) يتطلب تفعيل إضافة Zip في إعدادات PHP (extension=zip). يمكنك استخدام ملف CSV بدلاً منه حنى تفعيل الإضافة.');
                        }

                        Excel::import(new CustomersImport($import), Storage::path($filePath));

                        Notification::make()
                            ->title('تم الاستيراد بنجاح')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        $import->update([
                            'status' => ImportStatus::Failed->value,
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
