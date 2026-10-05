<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum EventType: string implements HasColor, HasIcon, HasLabel
{
    case Community = 'community';
    case Sports = 'sports';
    case Festival = 'festival';
    case Exhibition = 'exhibition';
    case Workshop = 'workshop';
    case Religious = 'religious';
    case College = 'college';
    case Other = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Community => 'Community gathering',
            self::Sports => 'Sports tournament',
            self::Festival => 'Festival / fair',
            self::Exhibition => 'Exhibition / expo',
            self::Workshop => 'Workshop / training',
            self::Religious => 'Religious event',
            self::College => 'College event',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return 'gray';
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Community => Heroicon::OutlinedUserGroup,
            self::Sports => Heroicon::OutlinedTrophy,
            self::Festival => Heroicon::OutlinedSparkles,
            self::Exhibition => Heroicon::OutlinedBuildingStorefront,
            self::Workshop => Heroicon::OutlinedAcademicCap,
            self::Religious => Heroicon::OutlinedSun,
            self::College => Heroicon::OutlinedBuildingLibrary,
            self::Other => Heroicon::OutlinedCalendar,
        };
    }
}
