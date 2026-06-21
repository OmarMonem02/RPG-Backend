<?php

namespace Tests\Unit;

use App\Support\SqlDialect;
use PHPUnit\Framework\TestCase;

class SqlDialectTest extends TestCase
{
    public function test_pgsql_have_commission_casts_to_int_for_boolean_or_smallint_columns(): void
    {
        $sql = SqlDialect::haveCommissionEnabled('products', 'pgsql');

        $this->assertSame('COALESCE((products.have_commission)::int, 0) = 1', $sql);
        $this->assertStringNotContainsString('IS TRUE', $sql);
    }

    public function test_mysql_have_commission_uses_integer_comparison(): void
    {
        $sql = SqlDialect::haveCommissionEnabled('products', 'mysql');

        $this->assertSame('COALESCE(products.have_commission, 0) = 1', $sql);
    }

    public function test_pgsql_month_period_uses_to_char(): void
    {
        $sql = SqlDialect::monthPeriodExpression('sales.created_at', 'pgsql');

        $this->assertSame("to_char(sales.created_at, 'YYYY-MM')", $sql);
    }

    public function test_mysql_month_period_uses_date_format(): void
    {
        $sql = SqlDialect::monthPeriodExpression('sales.created_at', 'mysql');

        $this->assertSame("DATE_FORMAT(sales.created_at, '%Y-%m')", $sql);
    }
}
