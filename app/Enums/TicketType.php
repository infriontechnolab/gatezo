<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The ticket types Gatezo offers in its own forms. attendees.ticket_type is not cast to
 * this: a CSV import keeps whatever the organizer's sheet says ("Gold", "Early bird").
 */
enum TicketType: string implements HasColor, HasIcon, HasLabel
{
    case General = 'general';
    case Vip = 'vip';
    case Guest = 'guest';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::General => 'General',
            self::Vip => 'VIP',
            self::Guest => 'Guest',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Vip => 'primary',
            default => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::General => Heroicon::OutlinedTicket,
            self::Vip => Heroicon::OutlinedStar,
            self::Guest => Heroicon::OutlinedUserPlus,
        };
    }
}
