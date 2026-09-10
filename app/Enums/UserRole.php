<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case TeamLeader = 'team_leader';
    case Sales = 'sales';

    /**
     * @return string Arabic label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            UserRole::Admin => 'مدير النظام',
            UserRole::TeamLeader => 'مدير الفريق',
            UserRole::Sales => 'مندوب المبيعات',
        };
    }

    /**
     * @return string Filament badge color for the role.
     */
    public function color(): string
    {
        return match ($this) {
            UserRole::Admin => 'danger',
            UserRole::TeamLeader => 'warning',
            UserRole::Sales => 'success',
        };
    }

    /**
     * @return string Heroicon name for the role.
     */
    public function icon(): string
    {
        return match ($this) {
            UserRole::Admin => 'heroicon-o-shield-check',
            UserRole::TeamLeader => 'heroicon-o-user-group',
            UserRole::Sales => 'heroicon-o-briefcase',
        };
    }
}
