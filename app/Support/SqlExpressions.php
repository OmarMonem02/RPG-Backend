<?php

namespace App\Support;

class SqlExpressions
{
    public static function profitAmount(): string
    {
        return '((sale_price + 0) - (cost_price + 0))';
    }

    public static function profitPercent(): string
    {
        $profit = self::profitAmount();

        return "CASE WHEN (cost_price + 0) > 0 THEN ({$profit} / (cost_price + 0)) * 100 ELSE 0 END";
    }
}
