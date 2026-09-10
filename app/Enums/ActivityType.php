<?php

namespace App\Enums;

enum ActivityType: string
{
    case Call = 'call';
    case WhatsApp = 'whatsapp';
    case SiteVisit = 'site_visit';
    case FollowUp = 'follow_up';
    case StatusChange = 'status_change';
    case Note = 'note';
    case Other = 'other';

    /**
     * @return string Arabic label for the activity type.
     */
    public function label(): string
    {
        return match ($this) {
            ActivityType::Call => 'مكالمة هاتفية',
            ActivityType::WhatsApp => 'واتساب',
            ActivityType::SiteVisit => 'زيارة موقع',
            ActivityType::FollowUp => 'متابعة',
            ActivityType::StatusChange => 'تغيير الحالة',
            ActivityType::Note => 'ملاحظة',
            ActivityType::Other => 'أخرى',
        };
    }

    /**
     * @return string Filament badge color for the activity type.
     */
    public function color(): string
    {
        return match ($this) {
            ActivityType::Call => 'success',
            ActivityType::WhatsApp => 'success',
            ActivityType::SiteVisit => 'warning',
            ActivityType::FollowUp => 'info',
            ActivityType::StatusChange => 'danger',
            ActivityType::Note => 'gray',
            ActivityType::Other => 'gray',
        };
    }

    /**
     * @return string Heroicon name for the activity type.
     */
    public function icon(): string
    {
        return match ($this) {
            ActivityType::Call => 'heroicon-o-phone',
            ActivityType::WhatsApp => 'heroicon-o-chat-bubble-left-right',
            ActivityType::SiteVisit => 'heroicon-o-map-pin',
            ActivityType::FollowUp => 'heroicon-o-clock',
            ActivityType::StatusChange => 'heroicon-o-arrow-path',
            ActivityType::Note => 'heroicon-o-document-text',
            ActivityType::Other => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }
}
