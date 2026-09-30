<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** A gate scan: someone came in, went out, or was turned away (never counts as a movement). */
enum CheckinDirection: string implements HasColor, HasIcon, HasLabel
{
    case In = 'in';
    case Out = 'out';
    case Denied = 'denied';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::In => 'In',
            self::Out => 'Out',
            self::Denied => 'Turned away',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'gray',
            self::Denied => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::In => Heroicon::OutlinedArrowRightEndOnRectangle,
            self::Out => Heroicon::OutlinedArrowLeftStartOnRectangle,
            self::Denied => Heroicon::OutlinedNoSymbol,
        };
    }
}
