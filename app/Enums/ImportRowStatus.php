<?php

namespace App\Enums;

enum ImportRowStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    /**
     * @return string Arabic label.
     */
    public function label(): string
    {
        return match ($this) {
            ImportRowStatus::Pending => 'في الانتظار',
            ImportRowStatus::Success => 'نجح',
            ImportRowStatus::Failed => 'فشل',
        };
    }

    /**
     * @return string Filament badge color.
     */
    public function color(): string
    {
        return match ($this) {
            ImportRowStatus::Pending => 'gray',
            ImportRowStatus::Success => 'success',
            ImportRowStatus::Failed => 'danger',
        };
    }
}
