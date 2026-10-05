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

    /** Digits with the country code, the form WhatsApp wants. Ten digits are Indian mobiles. */
    public static function international(?string $phone): ?string
    {
        $digits = self::normalise($phone);

        return $digits === null ? null : (strlen($digits) === 10 ? '91'.$digits : $digits);
    }

    /** Link that opens a WhatsApp chat with this number, message typed in. */
    public static function whatsappUrl(?string $phone, string $text): string
    {
        return 'https://wa.me/'.self::international($phone).'?text='.urlencode($text);
    }

    /** "Riya" and "Riya Shah" are the same person for the find-my-pass check; "Amit" is not. */
    public static function sameFirstName(string $a, string $b): bool
    {
        $first = fn (string $s) => mb_strtolower(trim(explode(' ', trim(preg_replace('/\s+/', ' ', $s)))[0]));

        return $first($a) !== '' && $first($a) === $first($b);
    }
}
