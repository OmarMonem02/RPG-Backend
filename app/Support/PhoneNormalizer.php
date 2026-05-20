<?php

namespace App\Support;

class PhoneNormalizer
{
    /**
     * Strip to digits only for comparison.
     */
    public static function digitsOnly(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * Compare two phone numbers (Egypt-friendly: 0-prefix vs 20 country code).
     */
    public static function matches(?string $a, ?string $b): bool
    {
        $digitsA = self::digitsOnly($a);
        $digitsB = self::digitsOnly($b);

        if ($digitsA === '' || $digitsB === '') {
            return false;
        }

        if ($digitsA === $digitsB) {
            return true;
        }

        $coreA = self::coreDigits($digitsA);
        $coreB = self::coreDigits($digitsB);

        return $coreA !== '' && $coreA === $coreB;
    }

    /**
     * Format phone for WhatsApp API (digits only, with country code, no +).
     */
    public static function forWhatsApp(?string $phone): string
    {
        $digits = self::digitsOnly($phone);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '20') && strlen($digits) >= 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '20' . substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '20' . $digits;
        }

        return $digits;
    }

    private static function coreDigits(string $digits): string
    {
        if (str_starts_with($digits, '20') && strlen($digits) > 2) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }
}
