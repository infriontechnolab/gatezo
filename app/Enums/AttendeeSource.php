<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** How an attendee got onto the list. History, so it is never edited after the fact. */
enum AttendeeSource: string implements HasColor, HasIcon, HasLabel
{
    case Online = 'online';
    case Walkup = 'walkup';
    case Import = 'import';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Online => 'Online',
            self::Walkup => 'Walk-up',
            self::Import => 'Imported',
        };
    }

    public function getColor(): string|array|null
    {
        return 'gray';
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Online => Heroicon::OutlinedGlobeAlt,
            self::Walkup => Heroicon::OutlinedQrCode,
            self::Import => Heroicon::OutlinedArrowUpTray,
        };
    }
}
