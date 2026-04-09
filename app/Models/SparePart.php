<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SparePart extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'image',
        'part_number',
        'stock_quantity',
        'low_stock_alarm',
        'spare_parts_category_id',
        'currency_pricing',
        'cost_price',
        'sale_price',
        'brand_id',
        'max_discount_type',
        'max_discount_value',
        'universal',
        'notes',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SparePartCategory::class, 'spare_parts_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
