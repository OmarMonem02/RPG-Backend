<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeBlueprint extends Model
{
    use SoftDeletes;

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

        return $query->where('model', 'like', "%{$search}%");
    }

    public function scopeByBrand($query, ?int $brandId)
    {
        return $brandId ? $query->where('brand_id', $brandId) : $query;
    }

    public function scopeByYear($query, ?int $year)
    {
        return $year ? $query->where('year', $year) : $query;
    }
}
