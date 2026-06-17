<?php

namespace App\Models;

use App\Models\Concerns\FiltersCatalogItems;
use App\Support\CaseInsensitiveLike;
use App\Support\ItemStatus;
use App\Traits\HasCatalogPricing;
use App\Traits\HasInventoryImages;
use App\Traits\HasInventoryTags;
use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenancePart extends Model
{
    use FiltersCatalogItems, HasCatalogPricing, HasInventoryImages, HasInventoryTags, LogsHistory, SoftDeletes;

    protected $table = 'maintenance_parts';

    protected $fillable = [
        'name',
        'sku',
        'part_number',
        'size',
        'color',
        'item_status',
        'stock_quantity',
        'low_stock_alarm',
        'maintenance_parts_category_id',
        'cost_currency',
        'sale_currency',
        'cost_price',
        'sale_price',
        'sale_price_mode',
        'sale_margin_type',
        'sale_margin_value',
        'brand_id',
        'max_discount_type',
        'max_discount_value',
        'universal',
        'have_commission',
        'notes',
        'tags',
    ];

    protected $casts = [
        'stock_quantity' => 'integer',
        'low_stock_alarm' => 'integer',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'sale_margin_value' => 'decimal:2',
        'max_discount_value' => 'decimal:2',
        'universal' => 'boolean',
        'have_commission' => 'boolean',
        'tags' => 'array',
        'item_status' => ItemStatus::class,
    ];

    public function category()
    {
        return $this->belongsTo(MaintenancePartCategory::class, 'maintenance_parts_category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function bikeBlueprintMaintenanceParts()
    {
        return $this->hasMany(BikeBlueprintMaintenancePart::class);
    }

    public function bikeBlueprints()
    {
        return $this->belongsToMany(BikeBlueprint::class, 'bike_blueprint_maintenance_parts', 'maintenance_part_id', 'bike_blueprint_id')
            ->withTimestamps()
            ->withTrashed()
            ->orderBy('bike_blueprints.model')
            ->orderBy('bike_blueprints.year');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $array = parent::toArray();

        if ($this->relationLoaded('bikeBlueprints')) {
            $array['bike_blueprint_ids'] = $this->bikeBlueprints->pluck('id')->values()->all();
        }

        return $this->appendInventoryImagesToArray($array);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function ticketItems()
    {
        return $this->hasMany(TicketItem::class);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'low_stock_alarm');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            CaseInsensitiveLike::where($q, 'name', $search);
            CaseInsensitiveLike::orWhere($q, 'sku', $search);
            CaseInsensitiveLike::orWhere($q, 'part_number', $search);
            $this->scopeSearchTags($q, $search);
        });
    }

    public function scopeByBrand($query, ?int $brandId)
    {
        return $brandId ? $query->where('brand_id', $brandId) : $query;
    }

    public function scopeByCategory($query, ?int $categoryId)
    {
        return $categoryId ? $query->where('maintenance_parts_category_id', $categoryId) : $query;
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
        return $currency ? $query->where('sale_currency', $currency) : $query;
    }

    public function scopeByBikeBrand($query, ?int $bikeBrandId)
    {
        if (! $bikeBrandId) {
            return $query;
        }

        return $query->where(function ($q) use ($bikeBrandId) {
            $q->whereHas('bikeBlueprints', function ($bp) use ($bikeBrandId) {
                $bp->where('brand_id', $bikeBrandId);
            })->orWhere('universal', true);
        });
    }

    public function scopeByBikeModel($query, ?string $bikeModel)
    {
        if (! $bikeModel) {
            return $query;
        }

        return $query->where(function ($q) use ($bikeModel) {
            $q->whereHas('bikeBlueprints', function ($bp) use ($bikeModel) {
                CaseInsensitiveLike::where($bp, 'model', $bikeModel);
            })->orWhere('universal', true);
        });
    }

    public function scopeByBikeYear($query, ?int $bikeYear)
    {
        if (! $bikeYear) {
            return $query;
        }

        return $query->where(function ($q) use ($bikeYear) {
            $q->whereHas('bikeBlueprints', function ($bp) use ($bikeYear) {
                $bp->where('year', $bikeYear);
            })->orWhere('universal', true);
        });
    }

    public function scopeByBikeYearRange($query, ?int $yearFrom, ?int $yearTo)
    {
        if ($yearFrom === null && $yearTo === null) {
            return $query;
        }

        return $query->where(function ($q) use ($yearFrom, $yearTo) {
            $q->whereHas('bikeBlueprints', function ($bp) use ($yearFrom, $yearTo) {
                if ($yearFrom !== null) {
                    $bp->where('year', '>=', $yearFrom);
                }
                if ($yearTo !== null) {
                    $bp->where('year', '<=', $yearTo);
                }
            })->orWhere('universal', true);
        });
    }
}
