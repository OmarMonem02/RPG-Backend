<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\Brand;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class BrandsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
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
        return 'Brands';
    }

    public function collection()
    {
        return Brand::query()->get();
    }

    protected function exportColumnMap(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'types' => 'Types',
        ];
    }

    protected function mapColumn(string $key, mixed $brand): mixed
    {
        /** @var Brand $brand */
        return match ($key) {
            'id' => $brand->id,
            'name' => $brand->name,
            'types' => implode(', ', $brand->types ?? []),
            default => null,
        };
    }
}
