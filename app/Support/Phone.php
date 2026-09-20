<?php

namespace App\Support;

/** One shape for phone numbers everywhere: digits only, Indian +91 / leading 0 stripped to the 10-digit form. */
final class Phone
{
    public static function normalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return substr($digits, 0, 20);
    }

    /** "Riya" and "Riya Shah" are the same person for the find-my-pass check; "Amit" is not. */
    public static function sameFirstName(string $a, string $b): bool
    {
        $first = fn (string $s) => mb_strtolower(trim(explode(' ', trim(preg_replace('/\s+/', ' ', $s)))[0]));

        return $first($a) !== '' && $first($a) === $first($b);
    }
}
