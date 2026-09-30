<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** A person's role on one event (the event_members pivot). */
enum MemberRole: string implements HasColor, HasIcon, HasLabel
{
    case Organizer = 'organizer';
    case Volunteer = 'volunteer';
    case Vendor = 'vendor';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Organizer => 'Organizer',
            self::Volunteer => 'Volunteer',
            self::Vendor => 'Vendor',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Organizer => 'primary',
            self::Volunteer => 'success',
            self::Vendor => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Organizer => Heroicon::OutlinedBriefcase,
            self::Volunteer => Heroicon::OutlinedUserGroup,
            self::Vendor => Heroicon::OutlinedBuildingStorefront,
        };
    }
}
