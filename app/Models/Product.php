<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const LOW_STOCK_THRESHOLD = 5;

    public const TYPE_PART = 'part';

    public const TYPE_ACCESSORY = 'accessory';

    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    public const DISCOUNT_TYPE_FIXED = 'fixed';

    protected $fillable = [
        'type',
        'name',
        'sku',
        'part_number',
        'category_id',
        'brand_id',
        'qty',
        'cost_price',
        'selling_price',
        'cost_price_usd',
        'max_discount_type',
        'max_discount_value',
        'is_universal',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'cost_price_usd' => 'decimal:2',
            'max_discount_value' => 'decimal:2',
            'is_universal' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function bikes(): BelongsToMany
    {
        return $this->belongsToMany(Bike::class);
    }

    public function stockLogs(): HasMany
    {
        return $this->hasMany(StockLog::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function calculateMaxDiscount(?float $amount = null): float
    {
        $baseAmount = $amount ?? (float) $this->selling_price;

        if ($this->max_discount_type === self::DISCOUNT_TYPE_PERCENTAGE) {
            return round($baseAmount * ((float) $this->max_discount_value / 100), 2);
        }

        return round((float) $this->max_discount_value, 2);
    }

    public function discountWithinLimit(float $discount, ?float $amount = null): bool
    {
        return round($discount, 2) <= $this->calculateMaxDiscount($amount);
    }
}
