<?php

namespace App\Support\Backup;

class SqlRestoreTransformer
{
  public function transform(string $sql, string $mode, string $driver): string
  {
    $sql = $this->stripManifest($sql);

    if ($mode === 'replace') {
      return $sql;
    }

    $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
    $output = [];

    foreach ($lines as $line) {
      $trimmed = trim($line);

      if ($trimmed === '' || str_starts_with($trimmed, '--')) {
        continue;
      }

      if ($this->isDdl($trimmed)) {
        continue;
      }

      if (preg_match('/^INSERT\s+/i', $trimmed)) {
        $output[] = $this->transformInsert($trimmed, $mode, $driver);
      }
    }

    return implode("\n", $output);
  }

  public function stripManifest(string $sql): string
  {
    $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
    $output = [];
    $inManifest = false;
    $manifestEnded = false;

    foreach ($lines as $line) {
      $trimmed = trim($line);

      if ($trimmed === BackupManifest::HEADER_START) {
        $inManifest = true;
        continue;
      }

      if ($inManifest) {
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
          continue;
        }

        $inManifest = false;
        $manifestEnded = true;
      }

      if (! $manifestEnded && str_starts_with($trimmed, '--')) {
        continue;
      }

      $output[] = $line;
    }

    return implode("\n", $output);
  }

  private function isDdl(string $line): bool
  {
    return (bool) preg_match('/^(CREATE|DROP|ALTER|TRUNCATE)\s+/i', $line);
  }

  private function transformInsert(string $line, string $mode, string $driver): string
  {
    if ($mode === 'merge') {
      return $this->toMergeInsert($line, $driver);
    }

    if ($mode === 'upsert') {
      return $this->toUpsertInsert($line, $driver);
    }

    return $line;
  }

  private function toMergeInsert(string $line, string $driver): string
  {
    if ($driver === 'sqlite') {
      return preg_replace('/^INSERT\s+INTO/i', 'INSERT OR IGNORE INTO', $line, 1) ?? $line;
    }

    return preg_replace('/^INSERT\s+INTO/i', 'INSERT IGNORE INTO', $line, 1) ?? $line;
  }

  private function toUpsertInsert(string $line, string $driver): string
  {
    if ($driver === 'sqlite') {
      if (! preg_match('/^INSERT\s+INTO\s+("|\')?([^"\']+)\1?\s*\(([^)]+)\)\s*VALUES\s*\((.+)\)\s*;?\s*$/is', $line, $matches)) {
        return $line;
      }

      $columns = array_map('trim', explode(',', $matches[3]));
      $updates = array_map(
        fn (string $column) => sprintf('%s = excluded.%s', $column, $column),
        $columns,
      );
      $conflictColumn = in_array('id', $columns, true) ? 'id' : $columns[0];
      $statement = rtrim($line, " \t\n\r\0\x0B;");

      return $statement . ' ON CONFLICT(' . $conflictColumn . ') DO UPDATE SET ' . implode(', ', $updates) . ';';
    }

    if (! preg_match('/^INSERT\s+INTO\s+(`[^`]+`|\w+)\s*\(([^)]+)\)\s*VALUES\s*\((.+)\)\s*;?\s*$/is', $line, $matches)) {
      return $line;
    }

    $columns = array_map('trim', explode(',', $matches[2]));
    $updates = array_map(
      fn (string $column) => sprintf('%s=VALUES(%s)', $column, $column),
      $columns,
    );

    $statement = rtrim($line, " \t\n\r\0\x0B;");

    return $statement . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates) . ';';
  }
}
