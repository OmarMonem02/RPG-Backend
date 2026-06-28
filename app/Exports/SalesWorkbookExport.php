<?php

namespace App\Exports;

use App\Services\SaleInventoryService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  list<string>|null  $salesColumnKeys
     * @param  list<string>|null  $itemColumnKeys
     */
    public function __construct(
        private readonly Builder $salesQuery,
        private readonly Builder $itemsQuery,
        private readonly SaleInventoryService $inventory,
        ?array $salesColumnKeys = null,
        ?array $itemColumnKeys = null,
    ) {
        $this->salesColumnKeys = $salesColumnKeys;
        $this->itemColumnKeys = $itemColumnKeys;
    }

    /** @var list<string>|null */
    private ?array $salesColumnKeys;

    /** @var list<string>|null */
    private ?array $itemColumnKeys;

    public function sheets(): array
    {
        return [
            new SalesListExport($this->salesQuery, $this->inventory, $this->salesColumnKeys),
            new SaleSoldItemsExport($this->itemsQuery, $this->inventory, $this->itemColumnKeys),
        ];
    }
}
