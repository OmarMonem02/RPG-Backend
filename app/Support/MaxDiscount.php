<?php

namespace App\Support;

final class MaxDiscount
{
    public static function maxLineDiscount(float $unitPrice, ?string $type, float $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        if ($type === 'percentage') {
            return ($unitPrice * $value) / 100;
        }

        return $value;
    }

    public static function clampLineDiscount(
        float $requested,
        float $unitPrice,
        ?string $type,
        float $value,
    ): float {
        $maxAllowed = min($unitPrice, self::maxLineDiscount($unitPrice, $type, $value));

        return max(0.0, min($requested, $maxAllowed));
    }
}
