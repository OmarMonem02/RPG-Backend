<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeBlueprint extends Model
{
    use SoftDeletes;

    protected $fillable = ['brand_id', 'model', 'year'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function bikesForSale(): HasMany
    {
        return $this->hasMany(BikeForSale::class);
    }

    public function customerBikes(): HasMany
    {
        return $this->hasMany(CustomerBike::class);
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(BikeBlueprintSparePart::class);
    }
}
