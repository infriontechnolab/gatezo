<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** Where a paid period stands today. Computed from its dates, never stored. */
enum SubscriptionStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Upcoming = 'upcoming';
    case Ended = 'ended';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Active => 'Active',
            self::Upcoming => 'Upcoming',
            self::Ended => 'Ended',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Upcoming => 'info',
            self::Ended => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Active => Heroicon::OutlinedCheckCircle,
            self::Upcoming => Heroicon::OutlinedCalendar,
            self::Ended => Heroicon::OutlinedArchiveBox,
        };
    }
}
