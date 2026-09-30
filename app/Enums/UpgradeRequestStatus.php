<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum UpgradeRequestStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Done = 'done';
    case Dismissed = 'dismissed';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Done => 'Paid',
            self::Dismissed => 'Dismissed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Done => 'success',
            self::Dismissed => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Dismissed => Heroicon::OutlinedXCircle,
        };
    }
}
