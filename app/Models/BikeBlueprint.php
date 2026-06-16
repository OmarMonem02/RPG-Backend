<?php

namespace App\Models;

use App\Support\CaseInsensitiveLike;
use App\Traits\LogsHistory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeBlueprint extends Model
{
    use SoftDeletes, LogsHistory;

    protected $table = 'bike_blueprints';

    protected $fillable = [
        'brand_id',
        'model',
        'year',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    // Relationships
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function bikeBlueprintSpareParts()
    {
        return $this->hasMany(BikeBlueprintSparePart::class);
    }

    public function spareParts()
    {
        return $this->belongsToMany(SparePart::class, 'bike_blueprint_spare_parts', 'bike_blueprint_id', 'spare_part_id')
            ->withTimestamps()
            ->withTrashed();
    }

    public function bikeBlueprintProducts()
    {
        return $this->hasMany(BikeBlueprintProduct::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'bike_blueprint_products', 'bike_blueprint_id', 'product_id')
            ->withTimestamps()
            ->withTrashed();
    }

    public function bikesForSale()
    {
        return $this->hasMany(BikeForSale::class);
    }

    public function customerBikes()
    {
        return $this->hasMany(CustomerBike::class);
    }

    // Scopes
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        $search = trim($search);
        $tokens = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($tokens) <= 1) {
            return $query->where(function ($q) use ($search) {
                $this->applyBlueprintTextOrYearMatch($q, $search);
            });
        }

        foreach ($tokens as $token) {
            $query->where(function ($q) use ($token) {
                $this->applyBlueprintTextOrYearMatch($q, $token);
            });
        }

        return $query;
    }

    public function scopeByBrand($query, ?int $brandId)
    {
        return $brandId ? $query->where('brand_id', $brandId) : $query;
    }

    public function scopeByBrandName($query, ?string $brandName)
    {
        if (!$brandName) {
            return $query;
        }

        $brandName = trim($brandName);

        return $query->whereHas('brand', function ($brandQuery) use ($brandName) {
            CaseInsensitiveLike::where($brandQuery, 'name', $brandName);
        });
    }

    public function scopeByModelName($query, ?string $model)
    {
        if (!$model) {
            return $query;
        }

        return CaseInsensitiveLike::where($query, 'model', trim($model));
    }

    public function scopeByYear($query, ?int $year)
    {
        return $year ? $query->where('year', $year) : $query;
    }

    private function applyBlueprintTextOrYearMatch($query, string $term): void
    {
        CaseInsensitiveLike::where($query, 'model', $term);
        $query->orWhereHas('brand', function ($brandQuery) use ($term) {
            CaseInsensitiveLike::where($brandQuery, 'name', $term);
        });

        if (ctype_digit($term) && strlen($term) === 4) {
            $query->orWhere('year', (int) $term);
        }
    }
}
