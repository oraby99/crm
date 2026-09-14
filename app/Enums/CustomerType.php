<?php

namespace App\Enums;

enum CustomerType: string
{
    case Client = 'client';
    case Engineer = 'engineer';
    case FinishingCompany = 'finishing_company';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'عميل',
            self::Engineer => 'مهندس / مكتب هندسي',
            self::FinishingCompany => 'شركة تشطيبات',
            self::Other => 'آخر',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Client => 'info',
            self::Engineer => 'warning',
            self::FinishingCompany => 'success',
            self::Other => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Client => 'heroicon-o-user',
            self::Engineer => 'heroicon-o-academic-cap',
            self::FinishingCompany => 'heroicon-o-building-office',
            self::Other => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }
}
