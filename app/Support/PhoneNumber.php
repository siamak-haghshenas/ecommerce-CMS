<?php

declare(strict_types=1);

namespace App\Support;

final class PhoneNumber
{
    /**
     * The normalized format every stored phone number uses: 09xxxxxxxxx.
     */
    public const string PATTERN = '/^09\d{9}$/';

    /**
     * Normalize an Iranian mobile number to the 09xxxxxxxxx format.
     *
     * Accepts Persian/Arabic digits, separators, and the +98, 0098 and 98
     * country prefixes. Input that cannot be normalized is returned trimmed
     * so validation can reject it.
     */
    public static function normalize(string $phoneNumber): string
    {
        $digits = preg_replace('/[\s\-().]/u', '', Digits::toLatin(trim($phoneNumber))) ?? '';

        $normalized = match (true) {
            str_starts_with($digits, '+98') => '0'.substr($digits, 3),
            str_starts_with($digits, '0098') => '0'.substr($digits, 4),
            str_starts_with($digits, '98') && strlen($digits) === 12 => '0'.substr($digits, 2),
            str_starts_with($digits, '9') && strlen($digits) === 10 => '0'.$digits,
            default => $digits,
        };

        return preg_match(self::PATTERN, $normalized) === 1 ? $normalized : trim($phoneNumber);
    }

    /**
     * Convert a normalized number to international format: +989xxxxxxxxx.
     */
    public static function toInternational(string $phoneNumber): string
    {
        return '+98'.substr($phoneNumber, 1);
    }
}
