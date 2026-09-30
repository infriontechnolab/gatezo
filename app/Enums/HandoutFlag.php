<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** Why the goodies counter warned. The value is also the result code the scanner shows. */
enum HandoutFlag: string implements HasColor, HasIcon, HasLabel
{
    case AlreadyCollected = 'already_collected';
    case NotCheckedIn = 'not_checked_in';
    case TicketType = 'ticket_type';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::AlreadyCollected => 'Already collected',
            self::NotCheckedIn => 'Not checked in',
            self::TicketType => 'Ticket not eligible',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AlreadyCollected => 'warning',
            self::NotCheckedIn => 'warning',
            self::TicketType => 'warning',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::AlreadyCollected => Heroicon::OutlinedExclamationTriangle,
            self::NotCheckedIn => Heroicon::OutlinedArrowRightEndOnRectangle,
            self::TicketType => Heroicon::OutlinedTicket,
        };
    }
}
