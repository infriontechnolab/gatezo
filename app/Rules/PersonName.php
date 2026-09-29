<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A person's name, as it will be read out at the gate and printed on a pass: letters in any
 * script (Gujarati, Devanagari, Latin… with their vowel signs), spaces, and . ' - as in
 * "R. K. D'Souza-Shah". At least two letters, so "%%%$$$$", "123" or "." don't become an
 * attendee that nobody can find again.
 */
class PersonName implements ValidationRule
{
    public const PATTERN = "/^[\\p{L}\\p{M}\\s.'’\\-]+$/u";

    public static function passes(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== ''
            && preg_match(self::PATTERN, $value) === 1
            && preg_match_all('/\p{L}/u', $value) >= 2;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return; // `required` decides whether a name is needed
        }
        if (! self::passes((string) $value)) {
            $fail('Enter a real name using letters only, e.g. Aarti Shah.');
        }
    }
}
