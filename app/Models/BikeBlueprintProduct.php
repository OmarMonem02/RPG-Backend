<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeBlueprintProduct extends Model
{
    use SoftDeletes;

    protected $table = 'bike_blueprint_products';

    protected $fillable = [
        'bike_blueprint_id',
        'product_id',
    ];

    public function bikeBlueprint()
    {
        return $this->belongsTo(BikeBlueprint::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
