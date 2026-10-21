<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class AttendanceIdentity
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (! preg_match('/^[0-9]{1,50}$/D', $value) || ltrim($value, '0') === '') {
            throw ValidationException::withMessages(['atms_id' => 'Use a nonzero numeric attendance ID of at most 50 digits.']);
        }

        return ltrim($value, '0');
    }
}
