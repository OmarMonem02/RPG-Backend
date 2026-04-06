<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Validation\ValidationException;

class ConvertUnitService
{
    public function toBaseUnits(Product $product, float $quantity, ?ProductUnit $unit = null): int
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'qty' => 'Quantity must be greater than zero.',
            ]);
        }

        $conversionFactor = (float) ($unit?->conversion_factor ?? 1);
        $baseQuantity = $quantity * $conversionFactor;

        if (abs($baseQuantity - round($baseQuantity)) > 0.0001) {
            throw ValidationException::withMessages([
                'qty' => 'Converted stock quantity must resolve to a whole base-unit amount.',
            ]);
        }

        return (int) round($baseQuantity);
    }

    public function fromBaseUnits(int $baseQuantity, ProductUnit $unit): float
    {
        $factor = (float) $unit->conversion_factor;

        if ($factor <= 0) {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit conversion factor must be greater than zero.',
            ]);
        }

        return round($baseQuantity / $factor, 4);
    }
}
