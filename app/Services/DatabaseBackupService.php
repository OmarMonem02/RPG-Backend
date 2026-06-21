<?php

namespace App\Services;

use App\Models\History;
use App\Models\User;
use App\Support\ApiCache;
use App\Support\Backup\BackupManifest;
use App\Support\Backup\SqlRestoreTransformer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
  public function __construct(
    private readonly SqlRestoreTransformer $transformer,
  ) {}

  /**
   * @return array{content: string, filename: string, manifest: array<string, mixed>}
   */
  public function export(): array
  {
    $driver = $this->driver();
    $manifest = $this->buildManifest($driver);
    $sql = $manifest->toSqlHeader() . $this->dumpDatabase($driver);
    $gzip = gzencode($sql, 9);

    if ($gzip === false) {
      throw new RuntimeException('Failed to compress backup.');
    }

    return [
      'content' => $gzip,
      'filename' => 'rpg-backup-' . now()->format('Ymd_His') . '.sql.gz',
      'manifest' => $manifest->toArray(),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function preview(UploadedFile $file): array
  {
    $sql = $this->readUploadedSql($file);
    $manifest = BackupManifest::parse($sql);

    if ($manifest === null) {
      throw new RuntimeException('Backup file is missing a valid manifest header.');
    }

    $warnings = $this->compatibilityWarnings($manifest);
    $insertCount = $this->countInsertStatements($sql);

    return [
      'manifest' => $manifest->toArray(),
      'tables' => $manifest->tableList(),
      'insert_statements' => $insertCount,
      'file_size_bytes' => $file->getSize(),
      'warnings' => $warnings,
      'compatible' => $warnings === [],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function import(UploadedFile $file, string $mode, ?User $user, ?string $confirmation = null): array
  {
    if ($mode === 'replace' && strtoupper((string) $confirmation) !== 'REPLACE') {
      throw new RuntimeException('Replace mode requires confirmation value REPLACE.');
    }

    $sql = $this->readUploadedSql($file);
    $manifest = BackupManifest::parse($sql);

    if ($manifest === null) {
      throw new RuntimeException('Backup file is missing a valid manifest header.');
    }

    $warnings = $this->compatibilityWarnings($manifest);

    if ($warnings !== []) {
      throw new RuntimeException(implode(' ', $warnings));
    }

    $driver = $this->driver();
    $effectiveMode = ($mode === 'replace' && $driver === 'sqlite') ? 'merge' : $mode;
    $transformed = $this->sanitizeSql($this->transformer->transform($sql, $effectiveMode, $driver));

    $runImport = function () use ($mode, $driver, $transformed): void {
      if ($driver === 'sqlite') {
        DB::connection()->getPdo()->exec('PRAGMA foreign_keys = OFF');
      } else {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
      }

      if ($mode === 'replace') {
        $this->prepareReplaceRestore($driver);
      }

      $this->executeSql($transformed, $driver);

      if ($driver === 'sqlite') {
        DB::connection()->getPdo()->exec('PRAGMA foreign_keys = ON');
      } else {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
      }
    };

    try {
      if ($driver === 'sqlite' || $mode === 'replace') {
        $runImport();
      } else {
        DB::transaction($runImport);
      }
    } catch (\Throwable $exception) {
      throw new RuntimeException(
        'Backup restore failed: ' . $exception->getMessage(),
        previous: $exception,
      );
    }

    Artisan::call('migrate', ['--force' => true]);

    $this->invalidateCaches();
    $this->logRestore($user, $mode, $manifest);

    return [
      'message' => 'System backup restored successfully. Database migrations were applied to match the current application schema.',
      'mode' => $mode,
      'manifest' => $manifest->toArray(),
      'insert_statements' => $this->countInsertStatements($transformed),
    ];
  }

  public function writeExportToPath(string $path): void
  {
    $export = $this->export();

    if (file_put_contents($path, $export['content']) === false) {
      throw new RuntimeException("Failed to write backup to {$path}.");
    }
  }

  private function driver(): string
  {
    return (string) config('database.default');
  }

  private function connectionConfig(): array
  {
    $connection = $this->driver();

    return (array) config("database.connections.{$connection}");
  }

  private function buildManifest(string $driver): BackupManifest
  {
    $config = $this->connectionConfig();
    $tables = $this->applicationTables();

    return BackupManifest::build([
      'driver' => $driver,
      'app_version' => (string) config('app.version', '1.0.0'),
      'created_at' => now()->toIso8601String(),
      'database' => (string) ($config['database'] ?? ''),
      'migration_batch' => (string) $this->currentMigrationBatch(),
      'tables' => implode(',', $tables),
    ]);
  }

  /**
   * @return list<string>
   */
  private function applicationTables(): array
  {
    $excluded = array_flip($this->excludedTables());
    $tables = array_values(array_filter(
      $this->listTables(),
      fn (string $table) => ! isset($excluded[$table]),
    ));

    $preferred = [
      'users',
      'sellers',
      'payment_methods',
      'settings',
      'brands',
      'product_categories',
      'spare_part_categories',
      'maintenance_part_categories',
      'maintenance_service_sectors',
      'bike_blueprints',
      'products',
      'spare_parts',
      'maintenance_parts',
      'maintenance_services',
      'bike_for_sale',
      'customers',
      'customer_bikes',
      'sales',
      'sale_items',
      'sale_adjustments',
      'customer_sale',
      'deliveries',
      'tickets',
      'ticket_tasks',
      'ticket_items',
      'ticket_messages',
      'expenses',
      'approval_requests',
      'inventory_images',
      'bike_blueprint_products',
      'bike_blueprint_spare_parts',
      'bike_blueprint_maintenance_parts',
      'personal_access_tokens',
      'histories',
    ];

    $ordered = [];

    foreach ($preferred as $table) {
      if (in_array($table, $tables, true)) {
        $ordered[] = $table;
      }
    }

    foreach ($tables as $table) {
      if (! in_array($table, $ordered, true)) {
        $ordered[] = $table;
      }
    }

    return $ordered;
  }

  /**
   * @return list<string>
   */
  private function excludedTables(): array
  {
    return array_values((array) config('backup.excluded_tables', []));
  }

  /**
   * @return list<string>
   */
  private function listTables(): array
  {
    $driver = $this->driver();

    if ($driver === 'sqlite') {
      $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

      return array_map(fn ($row) => (string) $row->name, $rows);
    }

    $database = (string) ($this->connectionConfig()['database'] ?? '');
    $rows = DB::select('SELECT TABLE_NAME as name FROM information_schema.tables WHERE table_schema = ? ORDER BY TABLE_NAME', [$database]);

    return array_map(fn ($row) => (string) $row->name, $rows);
  }

  private function currentMigrationBatch(): int
  {
    if (! Schema::hasTable('migrations')) {
      return 0;
    }

    return (int) (DB::table('migrations')->max('batch') ?? 0);
  }

  private function dumpDatabase(string $driver): string
  {
    return match ($driver) {
      'sqlite' => $this->dumpSqlite(),
      'mysql', 'mariadb' => $this->dumpMysql(),
      default => throw new RuntimeException("Database driver [{$driver}] is not supported for backup."),
    };
  }

  private function dumpMysql(): string
  {
    $binary = (string) config('backup.mysqldump_binary');

    if (! config('backup.force_php_dump', false) && $this->commandIsAvailable($binary)) {
      try {
        return $this->dumpMysqlWithCli($binary);
      } catch (RuntimeException) {
        // Fall back to PHP export when mysqldump fails at runtime.
      }
    }

    return $this->dumpMysqlViaPhp();
  }

  private function dumpMysqlWithCli(string $binary): string
  {
    $config = $this->connectionConfig();
    $database = (string) ($config['database'] ?? '');

    if ($database === '') {
      throw new RuntimeException('Database name is not configured.');
    }

    $command = [
      $binary,
      '--single-transaction',
      '--quick',
      '--routines',
      '--triggers',
      '--complete-insert',
      '--skip-extended-insert',
      '--host=' . ($config['host'] ?? '127.0.0.1'),
      '--port=' . ($config['port'] ?? '3306'),
      '--user=' . ($config['username'] ?? 'root'),
    ];

    foreach ($this->excludedTables() as $table) {
      $command[] = '--ignore-table=' . $database . '.' . $table;
    }

    $command[] = $database;

    $process = new Process($command, null, [
      'MYSQL_PWD' => (string) ($config['password'] ?? ''),
    ]);
    $process->setTimeout(null);
    $process->run();

    if (! $process->isSuccessful()) {
      throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput() ?: 'mysqldump failed.'));
    }

    return $process->getOutput();
  }

  private function dumpMysqlViaPhp(): string
  {
    $sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($this->applicationTables() as $table) {
      $create = DB::selectOne('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
      $createSql = null;

      if ($create !== null) {
        $createSql = $create->{'Create Table'} ?? $create->{'Create View'} ?? null;
      }

      if (is_string($createSql) && $createSql !== '') {
        $sql .= 'DROP TABLE IF EXISTS `' . $table . "`;\n";
        $sql .= $this->sanitizeSql($createSql) . ";\n\n";
      }

      foreach (DB::table($table)->orderByRaw('1')->cursor() as $row) {
        $record = (array) $row;
        $columns = array_keys($record);
        $values = array_map(fn ($value) => $this->quoteMysqlValue($value), array_values($record));

        $sql .= sprintf(
          'INSERT INTO `%s` (%s) VALUES (%s);' . "\n",
          $table,
          implode(', ', array_map(fn (string $column) => '`' . $column . '`', $columns)),
          implode(', ', $values),
        );
      }

      $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $sql;
  }

  private function quoteMysqlValue(mixed $value): string
  {
    if ($value === null) {
      return 'NULL';
    }

    if (is_bool($value)) {
      return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
      return (string) $value;
    }

    return DB::connection()->getPdo()->quote((string) $value);
  }

  private function commandIsAvailable(string $command): bool
  {
    if ($command === '') {
      return false;
    }

    if (str_contains($command, DIRECTORY_SEPARATOR) || str_contains($command, '/')) {
      return is_file($command);
    }

    $finder = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', $command]);
    $finder->run();

    return $finder->isSuccessful();
  }

  private function dumpSqlite(): string
  {
    return $this->dumpSqliteViaPhp();
  }

  private function dumpSqliteViaPhp(): string
  {
    $sql = '';

    foreach ($this->applicationTables() as $table) {
      $create = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$table]);

      if ($create?->sql) {
        $sql .= $this->sanitizeSql($create->sql) . ";\n\n";
      }

      $rows = DB::table($table)->orderByRaw('1')->get();

      foreach ($rows as $row) {
        $record = (array) $row;
        $columns = array_keys($record);
        $values = array_map(fn ($value) => $this->quoteSqlValue($value), array_values($record));

        $sql .= sprintf(
          'INSERT INTO "%s" (%s) VALUES (%s);' . "\n",
          $table,
          implode(', ', array_map(fn ($column) => '"' . $column . '"', $columns)),
          implode(', ', $values),
        );
      }

      $sql .= "\n";
    }

    return $sql;
  }

  private function quoteSqlValue(mixed $value): string
  {
    if ($value === null) {
      return 'NULL';
    }

    if (is_bool($value)) {
      return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
      return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
  }

  private function readUploadedSql(UploadedFile $file): string
  {
    $path = $file->getRealPath();

    if ($path === false) {
      throw new RuntimeException('Unable to read uploaded backup file.');
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
      throw new RuntimeException('Unable to read uploaded backup file.');
    }

    if (str_ends_with(strtolower($file->getClientOriginalName()), '.gz')) {
      $decoded = gzdecode($contents);

      if ($decoded === false) {
        throw new RuntimeException('Uploaded backup file is not a valid gzip archive.');
      }

      return $decoded;
    }

    return $contents;
  }

  /**
   * @return list<string>
   */
  private function compatibilityWarnings(BackupManifest $manifest): array
  {
    $warnings = [];

    if ($manifest->driver() !== $this->driver()) {
      $warnings[] = sprintf(
        'Backup driver [%s] does not match current database driver [%s].',
        $manifest->driver(),
        $this->driver(),
      );
    }

    $backupBatch = $manifest->migrationBatch();
    $currentBatch = $this->currentMigrationBatch();

    if ($backupBatch > 0 && $currentBatch > 0 && $backupBatch !== $currentBatch) {
      $warnings[] = sprintf(
        'Migration batch differs (backup: %d, current: %d). Schema mismatch may cause restore failures.',
        $backupBatch,
        $currentBatch,
      );
    }

    return $warnings;
  }

  private function countInsertStatements(string $sql): int
  {
    return preg_match_all('/^INSERT\s+/mi', $sql) ?: 0;
  }

  private function prepareReplaceRestore(string $driver): void
  {
    if ($driver === 'sqlite') {
      Artisan::call('migrate:fresh', ['--force' => true]);

      return;
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=0');

    foreach ($this->applicationTables() as $table) {
      DB::statement('DROP TABLE IF EXISTS `' . $table . '`');
    }
  }

  private function executeSql(string $sql, string $driver): void
  {
    if ($driver === 'mysql' || $driver === 'mariadb') {
      $this->executeMysqlSql($sql);

      return;
    }

    $this->executeSqliteSql($sql);
  }

  private function executeMysqlSql(string $sql): void
  {
    $config = $this->connectionConfig();
    $database = (string) ($config['database'] ?? '');

    $process = new Process([
      (string) config('backup.mysql_binary'),
      '--host=' . ($config['host'] ?? '127.0.0.1'),
      '--port=' . ($config['port'] ?? '3306'),
      '--user=' . ($config['username'] ?? 'root'),
      $database,
    ], null, [
      'MYSQL_PWD' => (string) ($config['password'] ?? ''),
    ]);
    $process->setInput($sql);
    $process->setTimeout(null);
    $process->run();

    if ($process->isSuccessful()) {
      return;
    }

    $this->executeSqlStatements($sql);
  }

  private function executeSqliteSql(string $sql): void
  {
    $this->executeSqlStatements($sql);
  }

  private function executeSqlStatements(string $sql): void
  {
    foreach ($this->splitSqlStatements($sql) as $statement) {
      if ($statement !== '') {
        DB::unprepared($statement);
      }
    }
  }

  /**
   * @return list<string>
   */
  private function splitSqlStatements(string $sql): array
  {
    $statements = [];
    $current = '';
    $length = strlen($sql);
    $inString = false;
    $delimiter = '';

    for ($index = 0; $index < $length; $index++) {
      $char = $sql[$index];
      $current .= $char;

      if ($inString) {
        if ($char === $delimiter && ($index === 0 || $sql[$index - 1] !== '\\')) {
          $inString = false;
          $delimiter = '';
        }

        continue;
      }

      if ($char === '\'' || $char === '"') {
        $inString = true;
        $delimiter = $char;
        continue;
      }

      if ($char === ';') {
        $statement = trim($current);

        if ($statement !== '') {
          $statements[] = $statement;
        }

        $current = '';
      }
    }

    $remaining = trim($current);

    if ($remaining !== '') {
      $statements[] = $remaining;
    }

    return $statements;
  }

  private function invalidateCaches(): void
  {
    ApiCache::invalidateTags([
      'products',
      'spare_parts',
      'maintenance_parts',
      'bikes',
      'brands',
      'bike_blueprints',
      'sales',
      'customers',
      'tickets',
      'settings',
      'payment_methods',
      'sellers',
      'history',
    ]);
  }

  private function logRestore(?User $user, string $mode, BackupManifest $manifest): void
  {
    History::create([
      'user_id' => $user?->id,
      'model_type' => 'system_backup',
      'model_id' => 0,
      'action' => 'restore',
      'before' => null,
      'after' => [
        'mode' => $mode,
        'manifest' => $manifest->toArray(),
      ],
      'ip_address' => request()?->ip(),
    ]);
  }

  private function sanitizeSql(string $sql): string
  {
    return str_replace('tickets_status_migration_old', 'tickets', $sql);
  }
}
