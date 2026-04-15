<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SparePart extends Model
{
    use SoftDeletes, LogsHistory;

    protected $table = 'spare_parts';

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

    protected $casts = [
        'stock_quantity' => 'integer',
        'low_stock_alarm' => 'integer',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'max_discount_value' => 'decimal:2',
        'universal' => 'boolean',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(SparePartCategory::class, 'spare_parts_category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function bikeBlueprintSpareParts()
    {
        return $this->hasMany(BikeBlueprintSparePart::class);
    }

    public function bikeBlueprints()
    {
        return $this->belongsToMany(BikeBlueprint::class, 'bike_blueprint_spare_parts', 'spare_part_id', 'bike_blueprint_id')
            ->withTimestamps()
            ->withTrashed();
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function ticketItems()
    {
        return $this->hasMany(TicketItem::class);
    }

    // Scopes
    public function scopeLowStock($query)
    {
        return $query->where('stock_quantity', '<=', 'low_stock_alarm');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where('name', 'like', "%{$search}%")
            ->orWhere('sku', 'like', "%{$search}%")
            ->orWhere('part_number', 'like', "%{$search}%");
    }

    public function scopeByBrand($query, ?int $brandId)
    {
        return $brandId ? $query->where('brand_id', $brandId) : $query;
    }

    public function scopeByCategory($query, ?int $categoryId)
    {
        return $categoryId ? $query->where('spare_parts_category_id', $categoryId) : $query;
    }

    public function scopeUniversal($query)
    {
        return $query->where('universal', true);
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
