<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** What the volunteer chose after the phone warned at the goodies counter. */
enum HandoutDecision: string implements HasColor, HasIcon, HasLabel
{
    case GaveAnyway = 'gave_anyway';
    case Refused = 'refused';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::GaveAnyway => 'Given anyway',
            self::Refused => 'Not given',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::GaveAnyway => 'warning',
            self::Refused => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::GaveAnyway => Heroicon::OutlinedGift,
            self::Refused => Heroicon::OutlinedXCircle,
        };
    }
}
