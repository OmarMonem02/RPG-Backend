<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES_WITH_PARTIAL = "'pending', 'in_progress', 'completed', 'partial', 'closed'";

    private const STATUSES_WITHOUT_PARTIAL = "'pending', 'in_progress', 'completed', 'closed'";

    private const SQLITE_STATUS_CHECK_WITHOUT_PARTIAL = '"status" varchar check ("status" in (\'pending\', \'in_progress\', \'completed\', \'closed\'))';

    private const SQLITE_STATUS_CHECK_WITH_PARTIAL = '"status" varchar check ("status" in (\'pending\', \'in_progress\', \'completed\', \'partial\', \'closed\'))';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE tickets MODIFY status ENUM('.self::STATUSES_WITH_PARTIAL.') NOT NULL'
            );

            return;
        }

        if ($driver === 'pgsql') {
            $this->replacePostgresStatusCheck(self::STATUSES_WITH_PARTIAL);

            return;
        }

        if ($driver === 'sqlite') {
            $this->replaceSqliteStatusCheck(
                self::SQLITE_STATUS_CHECK_WITHOUT_PARTIAL,
                self::SQLITE_STATUS_CHECK_WITH_PARTIAL
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::table('tickets')->where('status', 'partial')->update(['status' => 'completed']);

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE tickets MODIFY status ENUM('.self::STATUSES_WITHOUT_PARTIAL.') NOT NULL'
            );

            return;
        }

        if ($driver === 'pgsql') {
            $this->replacePostgresStatusCheck(self::STATUSES_WITHOUT_PARTIAL);

            return;
        }

        if ($driver === 'sqlite') {
            $this->replaceSqliteStatusCheck(
                self::SQLITE_STATUS_CHECK_WITH_PARTIAL,
                self::SQLITE_STATUS_CHECK_WITHOUT_PARTIAL
            );
        }
    }

    private function replaceSqliteStatusCheck(string $from, string $to): void
    {
        $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'tickets'");

        if ($row === null || ! isset($row->sql)) {
            return;
        }

        $newSql = str_replace($from, $to, $row->sql);

        if ($newSql === $row->sql) {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('ALTER TABLE tickets RENAME TO tickets_status_migration_old');
        DB::statement($newSql);
        DB::statement('INSERT INTO tickets SELECT * FROM tickets_status_migration_old');
        DB::statement('DROP TABLE tickets_status_migration_old');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function replacePostgresStatusCheck(string $allowedStatuses): void
    {
        $constraint = DB::selectOne("
            SELECT con.conname
            FROM pg_constraint con
            INNER JOIN pg_class rel ON rel.oid = con.conrelid
            INNER JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE nsp.nspname = current_schema()
              AND rel.relname = 'tickets'
              AND con.contype = 'c'
              AND pg_get_constraintdef(con.oid) LIKE '%status%'
        ");

        if ($constraint !== null) {
            DB::statement(sprintf(
                'ALTER TABLE tickets DROP CONSTRAINT "%s"',
                $constraint->conname
            ));
        }

        DB::statement(sprintf(
            'ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN (%s))',
            $allowedStatuses
        ));
    }
};
