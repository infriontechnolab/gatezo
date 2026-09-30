<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** Where a rostered shift stands right now. Computed from duty logs, never stored. */
enum ShiftStatus: string implements HasColor, HasIcon, HasLabel
{
    case Upcoming = 'upcoming';
    case Starting = 'starting';
    case Unlinked = 'unlinked';
    case OnDuty = 'on_duty';
    case Elsewhere = 'elsewhere';
    case Late = 'late';
    case Missed = 'missed';
    case Done = 'done';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Starting => 'Starting',
            self::Unlinked => 'Not joined yet',
            self::OnDuty => 'On duty',
            self::Elsewhere => 'At another post',
            self::Late => 'Late',
            self::Missed => 'Missed',
            self::Done => 'Done',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Upcoming => 'gray',
            self::Starting => 'gray',
            self::Unlinked => 'gray',
            self::OnDuty => 'success',
            self::Elsewhere => 'warning',
            self::Late => 'warning',
            self::Missed => 'danger',
            self::Done => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Upcoming => Heroicon::OutlinedCalendar,
            self::Starting => Heroicon::OutlinedClock,
            self::Unlinked => Heroicon::OutlinedUserMinus,
            self::OnDuty => Heroicon::OutlinedCheckCircle,
            self::Elsewhere => Heroicon::OutlinedArrowsRightLeft,
            self::Late => Heroicon::OutlinedExclamationTriangle,
            self::Missed => Heroicon::OutlinedXCircle,
            self::Done => Heroicon::OutlinedCheckBadge,
        };
    }
}
