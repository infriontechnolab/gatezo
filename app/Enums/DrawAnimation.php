<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** How the presenter screen reveals a winner. */
enum DrawAnimation: string implements HasColor, HasIcon, HasLabel
{
    case Roll = 'roll';
    case Wheel = 'wheel';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Roll => 'Rolling names',
            self::Wheel => 'Wheel',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Roll => 'primary',
            self::Wheel => 'warning',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Roll => Heroicon::OutlinedQueueList,
            self::Wheel => Heroicon::OutlinedArrowPath,
        };
    }
}
