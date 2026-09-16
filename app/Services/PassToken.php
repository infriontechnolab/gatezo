<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Pass;

/**
 * QR payload for an attendee pass.
 *
 * Format:  EQ1.<code>.<sig>
 *   code = pass.code (8 chars, unambiguous alphabet)
 *   sig  = first 16 hex chars of HMAC-SHA256(code, event.pass_secret)
 *
 * Short enough for a dense QR, and the scanner SPA can verify the signature
 * offline with WebCrypto once it has cached the event secret. Rotating
 * event.pass_secret invalidates every issued pass at once.
 */
final class PassToken
{
    public const VERSION = 'EQ1';

    public static function make(Pass $pass, ?Event $event = null): string
    {
        $event ??= $pass->event;

        return implode('.', [self::VERSION, $pass->code, self::sign($pass->code, $event->pass_secret)]);
    }

    public static function sign(string $code, string $secret): string
    {
        return substr(hash_hmac('sha256', $code, $secret), 0, 16);
    }

    /**
     * @return array{code: string, sig: string}|null null if the token is malformed
     */
    public static function parse(string $token): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            return null;
        }

        return ['code' => strtoupper($parts[1]), 'sig' => strtolower($parts[2])];
    }

    public static function verify(string $token, Event $event): bool
    {
        $parsed = self::parse($token);
        if ($parsed === null) {
            return false;
        }

        return hash_equals(self::sign($parsed['code'], $event->pass_secret), $parsed['sig']);
    }
}
