<?php

namespace App\Imports;

use App\Support\ImportExport\ImportRowProcessor;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class ProfessionalImport implements OnEachRow, WithHeadingRow
{
    private ImportRowProcessor $processor;

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    public function __construct(
        private readonly string $entity,
        private readonly array $definition,
    ) {
        $this->processor = new ImportRowProcessor();
    }

    public function onRow(Row $row): void
    {
        $this->rows[] = $this->processor->process(
            $this->entity,
            $this->definition,
            $row->toArray(),
            $row->getIndex(),
            true,
        );
    }

    public function response(string $message, array $columns): array
    {
        $summary = $this->processor->summarize($this->rows, $message);

        return [
            'message' => $message,
            'summary' => $summary,
            'columns' => $columns,
            'rows' => $this->rows,
            'errors' => $this->issues('error'),
            'warnings' => $this->issues('warning'),
            'skipped_duplicates' => collect($this->rows)
                ->where('status', 'duplicate')
                ->flatMap(fn (array $row) => collect($row['issues'])->pluck('message'))
                ->values()
                ->all(),
            'restored_records' => collect($this->rows)
                ->where('status', 'restored')
                ->map(fn (array $row) => "Row {$row['row_number']} restored.")
                ->values()
                ->all(),
            'created_count' => $summary['created_count'],
            'restored_count' => $summary['restored_count'],
            'skipped_count' => $summary['skipped_count'],
            'duplicate_count' => $summary['duplicate_count'],
            'valid_count' => $summary['valid_count'],
            'invalid_count' => $summary['invalid_count'],
        ];
    }

    private function issues(string $severity): array
    {
        return collect($this->rows)
            ->filter(fn (array $row) => $row['severity'] === $severity)
            ->flatMap(fn (array $row) => $row['issues'])
            ->values()
            ->all();
    }
}
