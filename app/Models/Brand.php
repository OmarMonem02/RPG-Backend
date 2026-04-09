<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'type'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }

    public function bikeBlueprints(): HasMany
    {
        return $this->hasMany(BikeBlueprint::class);
    }
}
