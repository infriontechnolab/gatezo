<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum DrawStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Finished = 'finished';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Draft => 'Not run',
            self::Ready => 'On stage',
            self::Finished => 'Finished',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ready => 'primary',
            self::Finished => 'success',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Ready => Heroicon::OutlinedPlayCircle,
            self::Finished => Heroicon::OutlinedFlag,
        };
    }
}
