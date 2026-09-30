<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** A volunteer checking on or off duty at a gate or zone. */
enum DutyStatus: string implements HasColor, HasIcon, HasLabel
{
    case On = 'on';
    case Off = 'off';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::On => 'On duty',
            self::Off => 'Off',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::On => 'success',
            self::Off => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::On => Heroicon::OutlinedCheckCircle,
            self::Off => Heroicon::OutlinedPauseCircle,
        };
    }
}
