<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportResults;
use App\Models\SparePart;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SparePartsImport implements OnEachRow, WithHeadingRow, WithValidation, SkipsOnError
{
    use SkipsErrors;
    use TracksImportResults;

    public function onRow(Row $row)
    {
        $rowData = $row->toArray();
        $lookupAttributes = ['sku' => $rowData['sku'] ?? null];
        $fillAttributes = [
            'name'                    => $rowData['name'] ?? null,
            'sku'                     => $rowData['sku'] ?? null,
            'part_number'             => $rowData['part_number'] ?? null,
            'stock_quantity'          => $rowData['stock_quantity'] ?? 0,
            'low_stock_alarm'         => $rowData['low_stock_alarm'] ?? 0,
            'spare_parts_category_id' => $rowData['category_id'] ?? null,
            'currency_pricing'        => $rowData['currency_pricing'] ?? null,
            'cost_price'              => $rowData['cost_price'] ?? null,
            'sale_price'              => $rowData['sale_price'] ?? null,
            'brand_id'                => $rowData['brand_id'] ?? null,
            'max_discount_type'       => $rowData['max_discount_type'] ?? null,
            'max_discount_value'      => $rowData['max_discount_value'] ?? null,
            'universal'               => isset($rowData['universal']) && in_array(strtolower($rowData['universal']), ['yes', '1', 'true']),
            'notes'                   => $rowData['notes'] ?? null,
        ];

        $restoredPart = $this->restoreMatchingRecord(
            SparePart::class,
            $lookupAttributes,
            $fillAttributes,
            'spare part',
            $row->getIndex(),
            $lookupAttributes
        );

        if ($restoredPart) {
            if (!empty($rowData['bike_blueprint_ids'])) {
                $ids = array_filter(array_map('trim', explode(',', $rowData['bike_blueprint_ids'])));
                if (!empty($ids)) {
                    $restoredPart->bikeBlueprints()->sync($ids);
                }
            }

            return;
        }

        if ($this->shouldSkipDuplicate(
            SparePart::class,
            $lookupAttributes,
            [$rowData['sku'] ?? null],
            'spare part',
            $row->getIndex(),
            $lookupAttributes
        )) {
            return;
        }

        $sparePart = SparePart::create([
            'name'                    => $rowData['name'] ?? null,
            'sku'                     => $rowData['sku'] ?? null,
            'part_number'             => $rowData['part_number'] ?? null,
            'stock_quantity'          => $rowData['stock_quantity'] ?? 0,
            'low_stock_alarm'         => $rowData['low_stock_alarm'] ?? 0,
            'spare_parts_category_id' => $rowData['category_id'] ?? null,
            'currency_pricing'        => $rowData['currency_pricing'] ?? null,
            'cost_price'              => $rowData['cost_price'] ?? null,
            'sale_price'              => $rowData['sale_price'] ?? null,
            'brand_id'                => $rowData['brand_id'] ?? null,
            'max_discount_type'       => $rowData['max_discount_type'] ?? null,
            'max_discount_value'      => $rowData['max_discount_value'] ?? null,
            'universal'               => isset($rowData['universal']) && in_array(strtolower($rowData['universal']), ['yes', '1', 'true']),
            'notes'                   => $rowData['notes'] ?? null,
        ]);

        $this->recordCreated();

        if (!empty($rowData['bike_blueprint_ids'])) {
            $ids = array_filter(array_map('trim', explode(',', $rowData['bike_blueprint_ids'])));
            if (!empty($ids)) {
                $sparePart->bikeBlueprints()->sync($ids);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'sku'         => 'required|string|max:100',
            'cost_price'  => 'nullable|numeric|min:0',
            'sale_price'  => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:spare_part_categories,id',
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
        if (isset($data['bike_blueprint_ids'])) {
            $data['bike_blueprint_ids'] = (string) $data['bike_blueprint_ids'];
        }

        return $data;
    }
}
