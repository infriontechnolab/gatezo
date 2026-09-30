<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** A drawn name: waiting its turn, on stage, collected the prize, or missed the claim window. */
enum WinnerStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Announced = 'announced';
    case Claimed = 'claimed';
    case Forfeited = 'forfeited';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Pending => 'Backup',
            self::Announced => 'On stage',
            self::Claimed => 'Claimed',
            self::Forfeited => 'Forfeited',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending, self::Forfeited => 'gray',
            self::Announced => 'primary',
            self::Claimed => 'success',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Announced => Heroicon::OutlinedMegaphone,
            self::Claimed => Heroicon::OutlinedGift,
            self::Forfeited => Heroicon::OutlinedXCircle,
        };
    }
}
