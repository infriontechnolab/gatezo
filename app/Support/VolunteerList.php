<?php

namespace App\Support;

use App\Rules\PersonName;
use App\Rules\PhoneNumber;

/**
 * A volunteer list pasted by the organizer, one person per line, the way it comes out of a
 * WhatsApp group or an Excel column: "Ravi Patel, 98240 17351", "2) Meera 9824017352",
 * "Nirav Shah" (no phone). Separators, numbering and bullets are all optional.
 */
final class VolunteerList
{
    /** @return array{people: list<array{name: string, phone: ?string}>, errors: list<string>} */
    public static function parse(string $text): array
    {
        $people = [];
        $errors = [];
        foreach (preg_split('/\R/u', $text) as $i => $raw) {
            $line = trim(preg_replace('/^\s*(?:\d{1,3}[.)]|[-•*~])\s*/u', '', $raw));
            if ($line === '') {
                continue;
            }
            $n = $i + 1;

            $phone = null;
            if (preg_match('/\+?\d[\d\s().-]{2,}\d/', $line, $m)) { // any run of 4+ digits is a phone attempt
                $line = str_replace($m[0], ' ', $line);
                $phone = validator(['phone' => $m[0]], ['phone' => [new PhoneNumber]])->passes() ? Phone::normalise($m[0]) : null;
                if ($phone === null) {
                    $errors[] = "Line {$n}: \"".trim($m[0]).'" is not a full phone number, added without one.';
                }
            }
            $name = trim(preg_replace('/\s+/u', ' ', $line), " \t,;:|-~");
            if ($name === '' || ! PersonName::passes($name)) {
                $errors[] = "Line {$n}: \"".str()->limit(trim($raw), 40).'" has no name.';

                continue;
            }
            $key = mb_strtolower($name);
            if (isset($people[$key])) {
                $people[$key]['phone'] ??= $phone;

                continue;
            }
            $people[$key] = ['name' => str()->limit($name, 60, ''), 'phone' => $phone];
        }

        return ['people' => array_values($people), 'errors' => $errors];
    }
}
