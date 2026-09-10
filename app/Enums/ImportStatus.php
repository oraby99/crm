<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * @return string Arabic label for the import status.
     */
    public function label(): string
    {
        return match ($this) {
            ImportStatus::Pending => 'في الانتظار',
            ImportStatus::Processing => 'جاري المعالجة',
            ImportStatus::Completed => 'مكتمل',
            ImportStatus::Failed => 'فشل',
        };
    }

    /**
     * @return string Filament badge color.
     */
    public function color(): string
    {
        return match ($this) {
            ImportStatus::Pending => 'gray',
            ImportStatus::Processing => 'warning',
            ImportStatus::Completed => 'success',
            ImportStatus::Failed => 'danger',
        };
    }

    /**
     * @return string Heroicon name.
     */
    public function icon(): string
    {
        return match ($this) {
            ImportStatus::Pending => 'heroicon-o-clock',
            ImportStatus::Processing => 'heroicon-o-arrow-path',
            ImportStatus::Completed => 'heroicon-o-check-circle',
            ImportStatus::Failed => 'heroicon-o-x-circle',
        };
    }
}
