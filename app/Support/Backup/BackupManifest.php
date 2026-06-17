<?php

namespace App\Support\Backup;

class BackupManifest
{
  public const HEADER_START = '-- RPG Backup Manifest';

  /**
   * @param  array<string, mixed>  $data
   */
  public function __construct(
    public readonly array $data,
  ) {}

  /**
   * @param  array<string, mixed>  $fields
   */
  public static function build(array $fields): self
  {
    return new self(array_merge([
      'version' => (string) config('backup.manifest_version', 1),
      'driver' => (string) ($fields['driver'] ?? ''),
      'app_version' => (string) ($fields['app_version'] ?? config('app.version', '1.0.0')),
      'created_at' => (string) ($fields['created_at'] ?? now()->toIso8601String()),
      'database' => (string) ($fields['database'] ?? ''),
      'migration_batch' => (string) ($fields['migration_batch'] ?? '0'),
      'tables' => (string) ($fields['tables'] ?? ''),
    ], $fields));
  }

  /**
   * @return array<string, mixed>
   */
  public function toArray(): array
  {
    return $this->data;
  }

  public function toSqlHeader(): string
  {
    $lines = [self::HEADER_START];

    foreach ($this->data as $key => $value) {
      if (is_array($value)) {
        $value = implode(',', $value);
      }

      $lines[] = sprintf('-- %s: %s', $key, $value);
    }

    return implode("\n", $lines) . "\n\n";
  }

  public static function parse(string $sql): ?self
  {
    $data = [];
    $inManifest = false;

    foreach (preg_split('/\r\n|\r|\n/', $sql) as $line) {
      $trimmed = trim($line);

      if ($trimmed === self::HEADER_START) {
        $inManifest = true;
        continue;
      }

      if (! $inManifest) {
        if ($data !== []) {
          break;
        }

        continue;
      }

      if (! str_starts_with($trimmed, '--')) {
        break;
      }

      $body = ltrim(substr($trimmed, 2));

      if ($body === '') {
        continue;
      }

      [$key, $value] = array_pad(explode(':', $body, 2), 2, '');
      $data[trim($key)] = trim($value);
    }

    if ($data === []) {
      return null;
    }

    return new self($data);
  }

  /**
   * @return list<string>
   */
  public function tableList(): array
  {
    $tables = $this->data['tables'] ?? '';

    if ($tables === '') {
      return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', (string) $tables))));
  }

  public function driver(): string
  {
    return (string) ($this->data['driver'] ?? '');
  }

  public function migrationBatch(): int
  {
    return (int) ($this->data['migration_batch'] ?? 0);
  }
}
