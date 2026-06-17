<?php

namespace App\Models;

use App\Support\CaseInsensitiveLike;
use App\Support\SqlExpressions;
use App\Traits\HasCatalogPricing;
use App\Traits\HasInventoryImages;
use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeForSale extends Model
{
    use HasCatalogPricing, HasInventoryImages, LogsHistory, SoftDeletes;

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return $this->appendInventoryImagesToArray(parent::toArray());
    }

    protected $table = 'bike_for_sale';

    protected $fillable = [
        'bike_blueprint_id',
        'cost_currency',
        'sale_currency',
        'cost_price',
        'sale_price',
        'sale_price_mode',
        'sale_margin_type',
        'sale_margin_value',
        'status',
        'max_discount_type',
        'max_discount_value',
        'vin',
        'mileage',
        'notes',
        'have_commission',
    ];

    protected $casts = [
        'have_commission' => 'boolean',
    ];

    public function bikeBlueprint(): BelongsTo
    {
        return $this->belongsTo(BikeBlueprint::class);
    }

    // Scopes
    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            CaseInsensitiveLike::where($q, 'vin', $search);
            $q->orWhereHas('bikeBlueprint', function ($bp) use ($search) {
                CaseInsensitiveLike::where($bp, 'model', $search);
            });
            $q->orWhereHas('bikeBlueprint.brand', function ($brand) use ($search) {
                CaseInsensitiveLike::where($brand, 'name', $search);
            });
        });
    }

    public function scopeByStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
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

    public function scopeByBlueprint($query, ?int $blueprintId)
    {
        return $blueprintId ? $query->where('bike_blueprint_id', $blueprintId) : $query;
    }

    public function scopeByCurrency($query, ?string $currency)
    {
        return $currency ? $query->where('sale_currency', $currency) : $query;
    }

    public function scopeByBlueprintBrand($query, ?int $brandId)
    {
        if (! $brandId) {
            return $query;
        }

        return $query->whereHas('bikeBlueprint', function ($bp) use ($brandId) {
            $bp->where('brand_id', $brandId);
        });
    }

    public function scopeByMileage($query, ?int $min = null, ?int $max = null)
    {
        if ($min !== null) {
            $query = $query->where('mileage', '>=', $min);
        }
        if ($max !== null) {
            $query = $query->where('mileage', '<=', $max);
        }

        return $query;
    }

    public function scopeByCostPrice($query, ?float $minPrice = null, ?float $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query = $query->where('cost_price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query = $query->where('cost_price', '<=', $maxPrice);
        }

        return $query;
    }

    public function scopeByMaxDiscount($query, ?float $min = null, ?float $max = null)
    {
        if ($min !== null) {
            $query = $query->where('max_discount_value', '>=', $min);
        }
        if ($max !== null) {
            $query = $query->where('max_discount_value', '<=', $max);
        }

        return $query;
    }

    public function scopeByProfitRange($query, ?float $min = null, ?float $max = null)
    {
        $profitSql = SqlExpressions::profitAmount();

        if ($min !== null) {
            $query = $query->whereRaw("{$profitSql} >= ?", [$min]);
        }
        if ($max !== null) {
            $query = $query->whereRaw("{$profitSql} <= ?", [$max]);
        }

        return $query;
    }

    public function scopeByProfitPercentRange($query, ?float $min = null, ?float $max = null)
    {
        $expression = SqlExpressions::profitPercent();

        if ($min !== null) {
            $query = $query->whereRaw("({$expression}) >= ?", [$min]);
        }
        if ($max !== null) {
            $query = $query->whereRaw("({$expression}) <= ?", [$max]);
        }

        return $query;
    }
}
