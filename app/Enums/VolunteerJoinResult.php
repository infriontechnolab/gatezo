<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** Outcome of one attempt to join the scanner, kept for the organizer's security log. */
enum VolunteerJoinResult: string implements HasColor, HasIcon, HasLabel
{
    case Ok = 'ok';
    case WrongCode = 'wrong_code';
    case NotOnRoster = 'not_on_roster';
    case Kicked = 'kicked';
    case LockedOut = 'locked_out';
    case CodeOff = 'code_off';
    case Invite = 'invite';
    case InviteUsed = 'invite_used';
    case InviteExpired = 'invite_expired';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Ok => 'Joined',
            self::WrongCode => 'Wrong code',
            self::NotOnRoster => 'Not on roster',
            self::Kicked => 'Removed',
            self::LockedOut => 'Locked out',
            self::CodeOff => 'Code joining off',
            self::Invite => 'Joined by link',
            self::InviteUsed => 'Link used on another phone',
            self::InviteExpired => 'Link expired',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Ok => 'success',
            self::WrongCode => 'warning',
            self::NotOnRoster => 'warning',
            self::Kicked => 'danger',
            self::LockedOut => 'danger',
            self::CodeOff => 'warning',
            self::Invite => 'success',
            self::InviteUsed => 'danger',
            self::InviteExpired => 'warning',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Ok => Heroicon::OutlinedCheckCircle,
            self::WrongCode => Heroicon::OutlinedKey,
            self::NotOnRoster => Heroicon::OutlinedUserMinus,
            self::Kicked => Heroicon::OutlinedNoSymbol,
            self::LockedOut => Heroicon::OutlinedLockClosed,
            self::CodeOff => Heroicon::OutlinedKey,
            self::Invite => Heroicon::OutlinedLink,
            self::InviteUsed => Heroicon::OutlinedDevicePhoneMobile,
            self::InviteExpired => Heroicon::OutlinedClock,
        };
    }
}
