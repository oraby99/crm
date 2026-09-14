<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Enums\ImportStatus;
use App\Filament\Resources\CustomerResource;
use App\Imports\CustomersImport;
use App\Models\Import;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'العملاء';

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $canImport = $user?->isAdmin() || $user?->isTeamLeader();

        $actions = [
            Actions\CreateAction::make()->label('إضافة عميل'),
        ];

        if ($canImport) {
            $actions[] = Actions\Action::make('download_sample')
                ->label('تحميل نموذج إكسيل')
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
                });

            $actions[] = Actions\Action::make('import_excel')
                ->label('استيراد من إكسيل')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->form([
                    FileUpload::make('file')
                        ->label('ملف الإكسيل (.xlsx / .xls / .csv)')
                        ->helperText('اختر ملف الإكسيل الذي يحتوي على بيانات العملاء.')
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

                    $import = Import::create([
                        'uploaded_by' => Auth::id(),
                        'file_name' => basename($filePath),
                        'file_path' => $filePath,
                        'status' => ImportStatus::Pending->value,
                    ]);

                    try {
                        if (! class_exists('\ZipArchive') && in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['xlsx', 'xls'])) {
                            throw new \Exception('قراءة ملفات الإكسيل (.xlsx) تتطلب تفعيل إضافة Zip في إعدادات PHP (extension=zip). يمكنك استخدام ملف CSV بدلاً منه حنى تفعيل الإضافة.');
                        }

                        Excel::import(new CustomersImport($import), Storage::path($filePath));

                        Notification::make()
                            ->title('تم استيراد الملف بنجاح')
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
                });
        }

        return $actions;
    }

    public function mount(): void
    {
        parent::mount();

        if (request()->has('alert_not_contacted')) {
            $this->activeTab = 'not_contacted';
        }

        if (request()->has('alert_sales_id')) {
            // In Filament 3, tableFilters is an array of state
            $this->tableFilters['sales_id'] = ['value' => request('alert_sales_id')];
        }
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),

            'follow_up_today' => Tab::make('متابعة اليوم')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('next_follow_up_at', today()))
                ->badge(fn () => CustomerResource::getEloquentQuery()->whereDate('next_follow_up_at', today())->count())
                ->badgeColor('warning'),

            'overdue' => Tab::make('متأخرة')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('next_follow_up_at', '<', today()))
                ->badge(fn () => CustomerResource::getEloquentQuery()->whereDate('next_follow_up_at', '<', today())->count())
                ->badgeColor('danger'),

            'not_contacted' => Tab::make('لم يتم التواصل')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('activities'))
                ->badge(fn () => CustomerResource::getEloquentQuery()->whereDoesntHave('activities')->count())
                ->badgeColor('gray'),
        ];
    }
}
