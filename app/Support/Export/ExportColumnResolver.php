<?php

namespace App\Support\Export;

use Illuminate\Validation\ValidationException;

class ExportColumnResolver
{
    public function __construct(private readonly ExportColumnCatalog $catalog) {}

    /**
     * @param  list<string>|string|null  $columnsInput
     * @return list<string>
     */
    public function resolve(
        string $context,
        array|string|null $columnsInput,
        ?string $entity = null,
        bool $includeExportOnly = true,
    ): array {
        $allowed = $this->catalog->keys($context, $entity, $includeExportOnly);

        if ($columnsInput === null || $columnsInput === '' || $columnsInput === []) {
            return $allowed;
        }

        $requested = is_array($columnsInput)
            ? array_values(array_filter(array_map('trim', $columnsInput)))
            : array_values(array_filter(array_map('trim', explode(',', (string) $columnsInput))));

        if ($requested === []) {
            throw ValidationException::withMessages([
                'columns' => ['At least one column must be selected.'],
            ]);
        }

        if (count($requested) !== count(array_unique($requested))) {
            throw ValidationException::withMessages([
                'columns' => ['Duplicate columns are not allowed.'],
            ]);
        }

        $unknown = array_values(array_diff($requested, $allowed));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'columns' => [
                    'Unknown column(s): ' . implode(', ', $unknown) . '. Allowed: ' . implode(', ', $allowed),
                ],
            ]);
        }

        return $requested;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<string>  $orderedKeys
     * @return list<array<string, mixed>>
     */
    public function reorderColumns(array $columns, array $orderedKeys): array
    {
        $byKey = collect($columns)->keyBy('key');

        return collect($orderedKeys)
            ->map(fn (string $key) => $byKey->get($key))
            ->filter()
            ->values()
            ->all();
    }
}
