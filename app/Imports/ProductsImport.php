<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportResults;
use App\Models\Product;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use RemembersRowNumber;
    use SkipsErrors;
    use TracksImportResults;

    public function model(array $row): ?Product
    {
        $lookupAttributes = ['sku' => $row['sku'] ?? null];
        $fillAttributes = [
            'name'                 => $row['name'] ?? null,
            'sku'                  => $row['sku'] ?? null,
            'part_number'          => $row['part_number'] ?? null,
            'stock_quantity'       => $row['stock_quantity'] ?? 0,
            'low_stock_alarm'      => $row['low_stock_alarm'] ?? 0,
            'products_category_id' => $row['category_id'] ?? null,
            'currency_pricing'     => $row['currency_pricing'] ?? null,
            'cost_price'           => $row['cost_price'] ?? null,
            'sale_price'           => $row['sale_price'] ?? null,
            'brand_id'             => $row['brand_id'] ?? null,
            'max_discount_type'    => $row['max_discount_type'] ?? null,
            'max_discount_value'   => $row['max_discount_value'] ?? null,
            'universal'            => isset($row['universal']) && in_array(strtolower($row['universal']), ['yes', '1', 'true']),
            'notes'                => $row['notes'] ?? null,
        ];

        if ($this->restoreMatchingRecord(
            Product::class,
            $lookupAttributes,
            $fillAttributes,
            'product',
            $this->getRowNumber(),
            $lookupAttributes
        )) {
            return null;
        }

        if ($this->shouldSkipDuplicate(
            Product::class,
            $lookupAttributes,
            [$row['sku'] ?? null],
            'product',
            $this->getRowNumber(),
            $lookupAttributes
        )) {
            return null;
        }

        $this->recordCreated();

        return new Product([
            'name'                 => $row['name'] ?? null,
            'sku'                  => $row['sku'] ?? null,
            'part_number'          => $row['part_number'] ?? null,
            'stock_quantity'       => $row['stock_quantity'] ?? 0,
            'low_stock_alarm'      => $row['low_stock_alarm'] ?? 0,
            'products_category_id' => $row['category_id'] ?? null,
            'currency_pricing'     => $row['currency_pricing'] ?? null,
            'cost_price'           => $row['cost_price'] ?? null,
            'sale_price'           => $row['sale_price'] ?? null,
            'brand_id'             => $row['brand_id'] ?? null,
            'max_discount_type'    => $row['max_discount_type'] ?? null,
            'max_discount_value'   => $row['max_discount_value'] ?? null,
            'universal'            => isset($row['universal']) && in_array(strtolower($row['universal']), ['yes', '1', 'true']),
            'notes'                => $row['notes'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'sku'         => 'required|string|max:100',
            'cost_price'  => 'nullable|numeric|min:0',
            'sale_price'  => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:product_categories,id',
            'brand_id'    => 'nullable|exists:brands,id',
        ];
    }

    public function prepareForValidation($data, $index)
    {
        if (isset($data['sku'])) {
            $data['sku'] = (string) $data['sku'];
        }
        if (isset($data['part_number'])) {
            $data['part_number'] = (string) $data['part_number'];
        }

        return $data;
    }

}
