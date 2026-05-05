<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use LogsHistory, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'image',
        'image_public_id',
        'part_number',
        'stock_quantity',
        'low_stock_alarm',
        'products_category_id',
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
        return $this->belongsTo(ProductCategory::class, 'products_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Scopes
    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where('name', 'like', "%{$search}%")
            ->orWhere('sku', 'like', "%{$search}%")
            ->orWhere('part_number', 'like', "%{$search}%");
    }

    public function scopeByCategory($query, ?int $categoryId)
    {
        return $categoryId ? $query->where('products_category_id', $categoryId) : $query;
    }

    public function scopeByBrand($query, ?int $brandId)
    {
        return $brandId ? $query->where('brand_id', $brandId) : $query;
    }

    public function scopeByPrice($query, ?float $minPrice = null, ?float $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query = $query->where('sale_price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query = $query->where('sale_price', '<=', $maxPrice);
        }

        return $query;
    }

    public function scopeByCurrency($query, ?string $currency)
    {
        return $currency ? $query->where('currency_pricing', $currency) : $query;
    }
}
