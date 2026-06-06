<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SparePart;

class StocktakeService
{
    /**
     * Build the list of discrepant rows for a stocktake count.
     *
     * The frontend only sends the counted quantities; the authoritative
     * system stock is re-read here so the report cannot be spoofed by stale
     * client data. Only rows where counted != system_quantity are returned.
     *
     * @param  array<int, array{type: string, id: int, counted: int}>  $items
     * @return array<int, array<string, mixed>>
     */
    public function buildDiscrepancies(array $items): array
    {
        $productIds = [];
        $sparePartIds = [];
        $countedByKey = [];

        foreach ($items as $item) {
            $type = (string) $item['type'];
            $id = (int) $item['id'];
            $counted = (int) $item['counted'];

            $countedByKey["{$type}:{$id}"] = $counted;

            if ($type === 'product') {
                $productIds[] = $id;
            } else {
                $sparePartIds[] = $id;
            }
        }

        $products = Product::query()
            ->whereIn('id', array_unique($productIds))
            ->get(['id', 'name', 'sku', 'part_number', 'stock_quantity']);

        $spareParts = SparePart::query()
            ->whereIn('id', array_unique($sparePartIds))
            ->get(['id', 'name', 'sku', 'part_number', 'stock_quantity']);

        $rows = [];

        foreach ($products as $product) {
            $row = $this->buildRow('product', 'Product', $product, $countedByKey["product:{$product->id}"] ?? null);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        foreach ($spareParts as $sparePart) {
            $row = $this->buildRow('spare_part', 'Spare Part', $sparePart, $countedByKey["spare_part:{$sparePart->id}"] ?? null);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRow(string $type, string $typeLabel, $model, ?int $counted): ?array
    {
        if ($counted === null) {
            return null;
        }

        $system = (int) $model->stock_quantity;
        $variance = $counted - $system;

        if ($variance === 0) {
            return null;
        }

        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'name' => $model->name,
            'sku' => $model->sku,
            'part_number' => $model->part_number,
            'system_quantity' => $system,
            'counted_quantity' => $counted,
            'variance' => $variance,
            'status' => $variance < 0 ? 'Shortage' : 'Surplus',
        ];
    }
}
