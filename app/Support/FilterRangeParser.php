<?php

namespace App\Support;

class FilterRangeParser
{
    /**
     * @return array{0: float|null, 1: float|null, 2: string|null}
     */
    public static function parseNumericRange(?string $range): array
    {
        if (! $range || ! str_contains($range, ':')) {
            return [null, null, null];
        }

        $parts = explode(':', $range, 2);
        if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            return [null, null, 'Invalid range format. Use "min:max" (e.g., "100:500")'];
        }

        $min = (float) $parts[0];
        $max = (float) $parts[1];

        if ($min > $max) {
            return [null, null, 'Invalid range: min must be less than or equal to max'];
        }

        return [$min, $max, null];
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    public static function parseIntBounds(mixed $min, mixed $max): array
    {
        $parsedMin = is_numeric($min) ? (int) $min : null;
        $parsedMax = is_numeric($max) ? (int) $max : null;

        return [$parsedMin, $parsedMax];
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    public static function parseFloatBounds(mixed $min, mixed $max): array
    {
        $parsedMin = is_numeric($min) ? (float) $min : null;
        $parsedMax = is_numeric($max) ? (float) $max : null;

        return [$parsedMin, $parsedMax];
    }

    public static function parseBooleanTriState(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($value, [false, 0, '0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return null;
    }
}
