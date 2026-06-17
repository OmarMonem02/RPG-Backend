<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use RuntimeException;

class BackupDatabaseCommand extends Command
{
  protected $signature = 'backup:database {--path= : Absolute path for the output .sql.gz file}';

  protected $description = 'Export a gzip-compressed SQL system backup';

  public function handle(DatabaseBackupService $backupService): int
  {
    $path = $this->option('path') ?: storage_path('app/backups/rpg-backup-' . now()->format('Ymd_His') . '.sql.gz');

    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
      $this->error("Unable to create directory: {$directory}");

      return self::FAILURE;
    }

    try {
      $backupService->writeExportToPath($path);
    } catch (RuntimeException $exception) {
      $this->error($exception->getMessage());

      return self::FAILURE;
    }

    $this->info("Backup written to {$path}");

    return self::SUCCESS;
  }
}
