<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** An attendee's ticket. Imported labels Gatezo doesn't know are kept in attendees.extra.ticket. */
enum TicketType: string implements HasColor, HasIcon, HasLabel
{
    case General = 'general';
    case Vip = 'vip';
    case Guest = 'guest';

    /** A ticket column from an imported sheet: "VIP", "guest ", "General" match; anything else is general. */
    public static function fromImport(?string $raw): self
    {
        return self::tryFrom(strtolower(trim((string) $raw))) ?? self::General;
    }

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
