<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shape check only: after cleanup it must look like a phone number (8 to 15 digits,
 * E.164's range). We cannot prove the number is theirs without an OTP; this stops
 * "abc" and "12345" from silently registering someone who can never find their pass.
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
        }
    }
}
