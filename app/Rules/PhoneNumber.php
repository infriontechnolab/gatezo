<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shape check only: after cleanup it must look like a phone number (8 to 15 digits,
 * E.164's range). We cannot prove the number is theirs without an OTP; this stops
 * "abc", "12345" and placeholder numbers like 0000000000 or 1234567890 from silently
 * registering someone who can never find their pass. Ten digits is an Indian mobile
 * (a +91 or leading 0 is stripped first), and those start with 6, 7, 8 or 9.
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }
        if (preg_match('/[^\d\s()+\-.]/', (string) $value)) {
            $fail('Enter a phone number using digits only.');

            return;
        }
        $digits = Phone::normalise((string) $value);
        if ($digits === null || strlen($digits) < 8 || strlen($digits) > 15) {
            $fail('That does not look like a full phone number. Enter 10 digits, or add the country code if you are outside India.');

            return;
        }
        if (self::isPlaceholder($digits)) {
            $fail('Enter your real mobile number so you can find your pass later.');

            return;
        }
        if (strlen($digits) === 10 && ! in_array($digits[0], ['6', '7', '8', '9'], true)) {
            $fail('Indian mobile numbers start with 6, 7, 8 or 9. Add the country code if yours is from outside India.');
        }
    }

    /** One digit repeated (0000000000), or a straight run up or down (1234567890, 9876543210). */
    private static function isPlaceholder(string $digits): bool
    {
        if (count(array_unique(str_split($digits))) === 1) {
            return true;
        }
        foreach ([1, 9] as $step) { // +1 or -1, wrapping 9 → 0
            $run = true;
            for ($i = 1; $i < strlen($digits); $i++) {
                if (((int) $digits[$i] - (int) $digits[$i - 1] + 10) % 10 !== $step) {
                    $run = false;
                    break;
                }
            }
            if ($run) {
                return true;
            }
        }

        return false;
    }
}
