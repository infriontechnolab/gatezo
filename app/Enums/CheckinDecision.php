<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** What the volunteer chose after the phone warned about a duplicate entry. */
enum CheckinDecision: string implements HasColor, HasIcon, HasLabel
{
    case LetIn = 'let_in';
    case TurnedAway = 'turned_away';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::LetIn => 'Let in anyway',
            self::TurnedAway => 'Turned away',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LetIn => 'warning',
            self::TurnedAway => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::LetIn => Heroicon::OutlinedCheckCircle,
            self::TurnedAway => Heroicon::OutlinedNoSymbol,
        };
    }
}
