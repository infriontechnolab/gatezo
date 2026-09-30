<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** Print kit templates. Classic is black on white for any printer; the others want colour. */
enum KitStyle: string implements HasColor, HasIcon, HasLabel
{
    case Classic = 'classic';
    case Bold = 'bold';
    case Festival = 'festival';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Classic => 'Classic · black on white, prints anywhere',
            self::Bold => 'Bold · accent header bands, rounded QR frames',
            self::Festival => 'Festival · full colour background',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Classic => 'gray',
            self::Bold => 'primary',
            self::Festival => 'warning',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Classic => Heroicon::OutlinedPrinter,
            self::Bold => Heroicon::OutlinedSwatch,
            self::Festival => Heroicon::OutlinedSparkles,
        };
    }
}
