<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\SparePartCategory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class SparePartCategoriesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use HasOrderedExportColumns;
    use StylesProfessionalSheets;

    /**
     * @param  list<string>|null  $columnKeys
     */
    public function __construct(?array $columnKeys = null)
    {
        $this->columnKeys = $columnKeys;
    }

    public function title(): string
    {
        return 'Spare Part Categories';
    }

    public function collection()
    {
        return SparePartCategory::query()->get();
    }

    protected function exportColumnMap(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
        ];
    }

    protected function mapColumn(string $key, mixed $category): mixed
    {
        /** @var SparePartCategory $category */
        return match ($key) {
            'id' => $category->id,
            'name' => $category->name,
            default => null,
        };
    }
}
