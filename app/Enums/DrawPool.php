<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** Who is eligible for a lucky draw. */
enum DrawPool: string implements HasColor, HasIcon, HasLabel
{
    case InsideNow = 'inside_now';
    case CheckedIn = 'checked_in';
    case Registered = 'registered';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::InsideNow => 'Inside now (latest scan is an entry)',
            self::CheckedIn => 'Checked in at least once',
            self::Registered => 'Everyone registered',
        };
    }

    public function getColor(): string|array|null
    {
        return 'gray';
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::InsideNow => Heroicon::OutlinedMapPin,
            self::CheckedIn => Heroicon::OutlinedCheckBadge,
            self::Registered => Heroicon::OutlinedUsers,
        };
    }
}
