<?php

namespace App\Support;

final class SqlDialect
{
    public static function driver(): string
    {
        return (string) config('database.default');
    }

    public static function connectionDriver(): string
    {
        return (string) \Illuminate\Support\Facades\DB::connection()->getDriverName();
    }

    public static function haveCommissionEnabled(string $tableAlias, ?string $driver = null): string
    {
        $driver ??= self::connectionDriver();

        return match ($driver) {
            // Cast to int so this works for both boolean and smallint columns (common after MySQL→Postgres restores).
            'pgsql' => "COALESCE(({$tableAlias}.have_commission)::int, 0) = 1",
            default => "COALESCE({$tableAlias}.have_commission, 0) = 1",
        };
    }

    public static function monthPeriodExpression(string $column, ?string $driver = null): string
    {
        $driver ??= self::connectionDriver();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * @param  list<string>  $expressions
     */
    public static function greatest(array $expressions, ?string $driver = null): string
    {
        $driver ??= self::connectionDriver();

        if ($expressions === []) {
            return '0';
        }

        if ($driver === 'sqlite') {
            $sql = $expressions[0];
            for ($i = 1; $i < count($expressions); $i++) {
                $sql = "MAX({$sql}, {$expressions[$i]})";
            }

            return $sql;
        }

        return 'GREATEST('.implode(', ', $expressions).')';
    }
}
