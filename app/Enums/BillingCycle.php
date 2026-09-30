<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum BillingCycle: string implements HasColor, HasIcon, HasLabel
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Monthly => 'gray',
            self::Yearly => 'primary',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Monthly => Heroicon::OutlinedCalendar,
            self::Yearly => Heroicon::OutlinedCalendarDateRange,
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }

    /** "month" / "year", for price labels like "₹499 / month". */
    public function unit(): string
    {
        return match ($this) {
            self::Monthly => 'month',
            self::Yearly => 'year',
        };
    }
}
