<?php

namespace App\Imports\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

trait TracksImportResults
{
    protected int $createdCount = 0;

    protected int $restoredCount = 0;

    protected int $skippedCount = 0;

    /** @var array<string, bool> */
    protected array $seenDuplicateKeys = [];

    /** @var array<int, string> */
    protected array $skippedDuplicates = [];

    /** @var array<int, string> */
    protected array $restoredRecords = [];

    protected function shouldSkipDuplicate(
        string $modelClass,
        array $lookupAttributes,
        array $keyParts,
        string $entityLabel,
        ?int $rowNumber,
        array $messageAttributes = []
    ): bool {
        $duplicateKey = $this->buildDuplicateKey($keyParts ?: $lookupAttributes);

        if (isset($this->seenDuplicateKeys[$duplicateKey])) {
            $this->recordDuplicate($entityLabel, $rowNumber, $messageAttributes ?: $lookupAttributes);

            return true;
        }

        $this->seenDuplicateKeys[$duplicateKey] = true;

        if ($this->newDuplicateQuery($modelClass)->where($lookupAttributes)->exists()) {
            $this->recordDuplicate($entityLabel, $rowNumber, $messageAttributes ?: $lookupAttributes);

            return true;
        }

        return false;
    }

    protected function restoreMatchingRecord(
        string $modelClass,
        array $lookupAttributes,
        array $fillAttributes,
        string $entityLabel,
        ?int $rowNumber,
        array $messageAttributes = []
    ): mixed {
        if (! in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            return null;
        }

        $record = $modelClass::withTrashed()->where($lookupAttributes)->first();

        if (! $record || ! method_exists($record, 'trashed') || ! $record->trashed()) {
            return null;
        }

        $record->fill($fillAttributes);
        $record->restore();
        $record->save();

        $this->restoredCount++;
        $this->recordRestored($entityLabel, $rowNumber, $messageAttributes ?: $lookupAttributes);

        return $record;
    }

    protected function recordCreated(): void
    {
        $this->createdCount++;
    }

    public function createdCount(): int
    {
        return $this->createdCount;
    }

    public function restoredCount(): int
    {
        return $this->restoredCount;
    }

    public function skippedCount(): int
    {
        return $this->skippedCount;
    }

    /**
     * @return array<int, string>
     */
    public function skippedDuplicates(): array
    {
        return $this->skippedDuplicates;
    }

    /**
     * @return array<int, string>
     */
    public function restoredRecords(): array
    {
        return $this->restoredRecords;
    }

    protected function newDuplicateQuery(string $modelClass): Builder
    {
        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query;
    }

    protected function recordDuplicate(string $entityLabel, ?int $rowNumber, array $attributes): void
    {
        $this->skippedCount++;

        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = $key . '=' . $value;
        }

        $context = empty($parts) ? '' : ' (' . implode(', ', $parts) . ')';
        $rowText = $rowNumber ? "Row {$rowNumber}" : 'A row';

        $this->skippedDuplicates[] = "{$rowText} skipped duplicate {$entityLabel}{$context}.";
    }

    protected function recordRestored(string $entityLabel, ?int $rowNumber, array $attributes): void
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = $key . '=' . $value;
        }

        $context = empty($parts) ? '' : ' (' . implode(', ', $parts) . ')';
        $rowText = $rowNumber ? "Row {$rowNumber}" : 'A row';

        $this->restoredRecords[] = "{$rowText} restored deleted {$entityLabel}{$context}.";
    }

    protected function buildDuplicateKey(array $parts): string
    {
        $normalized = array_map(function ($value) {
            if (is_string($value)) {
                return mb_strtolower(trim($value));
            }

            if (is_bool($value)) {
                return $value ? 1 : 0;
            }

            return $value;
        }, $parts);

        return md5(json_encode(array_values($normalized), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
