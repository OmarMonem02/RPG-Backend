<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProductsService
{
    public function export(string $format = 'csv'): StreamedResponse
    {
        $products = Product::query()->with(['units', 'bikes'])->get();

        return $this->buildResponse($this->mapProducts($products->all()), $format, 'products_export');
    }

    public function template(string $format = 'csv'): StreamedResponse
    {
        return $this->buildResponse([], $format, 'products_import_template');
    }

    private function mapProducts(array $products): array
    {
        return array_map(function (Product $product): array {
            return [
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'qty' => $product->qty,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
                'cost_price_usd' => $product->cost_price_usd,
                'max_discount_type' => $product->max_discount_type,
                'max_discount_value' => $product->max_discount_value,
                'is_universal' => $product->is_universal ? 1 : 0,
                'part_number' => $product->part_number,
                'description' => $product->description,
                'bike_ids' => $product->bikes->pluck('id')->implode(','),
                'units_json' => $product->units->map(fn ($unit) => [
                    'id' => $unit->id,
                    'unit_name' => $unit->unit_name,
                    'conversion_factor' => $unit->conversion_factor,
                    'price' => $unit->price,
                ])->values()->toJson(),
            ];
        }, $products);
    }

    private function buildResponse(array $rows, string $format, string $baseFilename): StreamedResponse
    {
        $headers = ['sku', 'name', 'type', 'category_id', 'brand_id', 'qty', 'cost_price', 'selling_price', 'cost_price_usd', 'max_discount_type', 'max_discount_value', 'is_universal', 'part_number', 'description', 'bike_ids', 'units_json'];
        $delimiter = $format === 'excel' ? "\t" : ',';
        $extension = $format === 'excel' ? 'xls' : 'csv';
        $contentType = $format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv';

        return response()->streamDownload(function () use ($headers, $rows, $delimiter): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers, $delimiter);

            foreach ($rows as $row) {
                fputcsv($handle, $row, $delimiter);
            }

            fclose($handle);
        }, $baseFilename.'.'.$extension, [
            'Content-Type' => $contentType,
        ]);
    }
}
