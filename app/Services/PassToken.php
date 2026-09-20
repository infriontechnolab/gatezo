<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Pass;

/**
 * QR payload for an attendee pass.
 *
 * Static:    EQ1.<code>.<sig>
 *   sig  = first 16 hex of HMAC-SHA256(code, event.pass_secret). Never changes; a screenshot
 *   of it works all night (the scanner's "already inside" screen is the only guard).
 *
 * Rotating:  EQ2.<code>.<slot>.<mac>    (events with strict_passes on)
 *   slot = floor(unix / 30), mac = first 16 hex of HMAC-SHA256(slot, sig). The pass page
 *   fetches a fresh one every 30 s, so a screenshot dies within a minute or so. The scanner
 *   already caches `sig` per pass, so it verifies EQ2 offline too (slot within ±1 of its clock).
 *
 * Rotating event.pass_secret invalidates every issued pass of either kind.
 */
final class PassToken
{
    public const VERSION = 'EQ1';

    public const ROTATING = 'EQ2';

    public const SLOT_SECONDS = 30;

    /** Slots either side of "now" that still verify: clock skew + the walk from phone to scanner. */
    public const SLOT_WINDOW = 1;

    public static function make(Pass $pass, ?Event $event = null): string
    {
        $event ??= $pass->event;

        return implode('.', [self::VERSION, $pass->code, self::sign($pass->code, $event->pass_secret)]);
    }

    /** The token the attendee should show right now: rotating when the event is strict. */
    public static function current(Pass $pass, ?Event $event = null): string
    {
        $event ??= $pass->event;

        return $event->strict_passes ? self::rotating($pass, $event) : self::make($pass, $event);
    }

    public static function rotating(Pass $pass, ?Event $event = null, ?int $slot = null): string
    {
        $event ??= $pass->event;
        $slot ??= self::slot();
        $sig = self::sign($pass->code, $event->pass_secret);

        return implode('.', [self::ROTATING, $pass->code, $slot, self::mac($slot, $sig)]);
    }

    public static function sign(string $code, string $secret): string
    {
        return substr(hash_hmac('sha256', $code, $secret), 0, 16);
    }

    public static function mac(int $slot, string $sig): string
    {
        return substr(hash_hmac('sha256', (string) $slot, $sig), 0, 16);
    }

    public static function slot(?int $time = null): int
    {
        return intdiv($time ?? time(), self::SLOT_SECONDS);
    }

    /** Seconds until the current slot rolls over (for the pass page countdown). */
    public static function secondsLeft(): int
    {
        return self::SLOT_SECONDS - (time() % self::SLOT_SECONDS);
    }

    /**
     * @return array{version: string, code: string, sig: ?string, slot: ?int, mac: ?string}|null null if malformed
     */
    public static function parse(string $token): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) === 3 && $parts[0] === self::VERSION) {
            return ['version' => self::VERSION, 'code' => strtoupper($parts[1]), 'sig' => strtolower($parts[2]), 'slot' => null, 'mac' => null];
        }
        if (count($parts) === 4 && $parts[0] === self::ROTATING && ctype_digit($parts[2])) {
            return ['version' => self::ROTATING, 'code' => strtoupper($parts[1]), 'sig' => null, 'slot' => (int) $parts[2], 'mac' => strtolower($parts[3])];
        }

        return null;
    }

    /**
     * True when the token is genuine for this event. In strict mode a static EQ1 token
     * is refused: it is exactly the screenshot strict mode exists to stop.
     */
    public static function verify(string $token, Event $event, ?int $now = null): bool
    {
        $parsed = self::parse($token);
        if ($parsed === null) {
            return false;
        }
        $sig = self::sign($parsed['code'], $event->pass_secret);

        if ($parsed['version'] === self::VERSION) {
            return ! $event->strict_passes && hash_equals($sig, $parsed['sig']);
        }

        return abs($parsed['slot'] - self::slot($now)) <= self::SLOT_WINDOW
            && hash_equals(self::mac($parsed['slot'], $sig), $parsed['mac']);
    }

    /** Why verify() said no, for a scanner message. */
    public static function failure(string $token, Event $event): string
    {
        $parsed = self::parse($token);
        if ($parsed === null) {
            return 'invalid_signature';
        }
        if ($parsed['version'] === self::VERSION && $event->strict_passes) {
            return 'static_pass';
        }
        if ($parsed['version'] === self::ROTATING && abs($parsed['slot'] - self::slot()) > self::SLOT_WINDOW
            && hash_equals(self::mac($parsed['slot'], self::sign($parsed['code'], $event->pass_secret)), $parsed['mac'])) {
            return 'expired_pass';
        }

        return 'invalid_signature';
    }
}
