<?php

namespace App\Exports\Concerns;

trait HasOrderedExportColumns
{
    /** @var list<string>|null */
    protected ?array $columnKeys = null;

    /**
     * @return array<string, string> key => heading label
     */
    abstract protected function exportColumnMap(): array;

    abstract protected function mapColumn(string $key, mixed $model): mixed;

    /**
     * @return list<string>
     */
    protected function resolvedColumnKeys(): array
    {
        return $this->columnKeys ?? array_keys($this->exportColumnMap());
    }

    public function headings(): array
    {
        $map = $this->exportColumnMap();

        return array_map(
            fn (string $key) => $map[$key],
            $this->resolvedColumnKeys(),
        );
    }

  /**
   * @param  mixed  $model
   * @return list<mixed>
   */
    public function map($model): array
    {
        return array_map(
            fn (string $key) => $this->mapColumn($key, $model),
            $this->resolvedColumnKeys(),
        );
    }
}
